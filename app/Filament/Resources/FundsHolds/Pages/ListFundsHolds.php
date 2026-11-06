<?php

namespace App\Filament\Resources\FundsHolds\Pages;

use App\Enums\PermissionName;
use App\Filament\Resources\FundsHolds\FundsHoldResource;
use App\Models\Account;
use App\Services\HoldService;
use App\Services\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ListFundsHolds extends ListRecords
{
    protected static string $resource = FundsHoldResource::class;

    private static function accountLabel(Account $account): string
    {
        return "{$account->user?->name} •••• {$account->account_number_last_four} ({$account->currency})";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('place')
                ->label('Place hold')
                ->visible(fn (): bool => auth()->user()->can(PermissionName::PlaceHolds->value))
                ->schema([
                    Select::make('account_id')->label('Account')->required()->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Account::query()
                            ->with('user')
                            ->where('account_number_last_four', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->limit(20)->get()
                            ->mapWithKeys(fn (Account $a): array => [$a->id => self::accountLabel($a)])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($account = Account::query()->with('user')->whereKey($value)->first()) ? self::accountLabel($account) : null),
                    TextInput::make('amount')->required()->numeric()->minValue(0.01),
                    DateTimePicker::make('expires_at')->after('now'),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $account = Account::query()->whereKey($data['account_id'])->firstOrFail();
                    $minor = Money::toMinor((string) $data['amount']);

                    try {
                        app(HoldService::class)->place(
                            $account,
                            auth()->user(),
                            $minor ?? 0,
                            $data['reason'],
                            filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Hold placed')->success()->send();
                }),
        ];
    }
}
