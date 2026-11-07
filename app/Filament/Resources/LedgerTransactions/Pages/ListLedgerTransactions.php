<?php

namespace App\Filament\Resources\LedgerTransactions\Pages;

use App\Enums\LedgerDirection;
use App\Enums\PermissionName;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use App\Models\Account;
use App\Services\LedgerService;
use App\Services\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLedgerTransactions extends ListRecords
{
    use NotifiesServiceErrors;

    protected static string $resource = LedgerTransactionResource::class;

    /**
     * Accounts matching the search (all of them, capped, when empty) keyed by id.
     *
     * @return array<int, string>
     */
    private static function accountOptions(string $search = ''): array
    {
        return Account::query()->with('user')
            ->where('account_number_last_four', 'like', "%{$search}%")
            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->limit(50)->get()
            ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->user?->name} {$a->maskedNumber()} ({$a->currency})"])->all();
    }

    /**
     * Append a manual adjustment; existing rows are never edited.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('postAdjustment')
                ->label('Post adjustment')
                ->visible(fn (): bool => auth()->user()->can(PermissionName::AdjustBalances->value))
                ->schema([
                    Select::make('account_id')->label('Account')->required()->searchable()
                        ->preload()
                        ->options(fn (): array => self::accountOptions())
                        ->getSearchResultsUsing(fn (string $search): array => self::accountOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => ($a = Account::query()->with('user')->whereKey($value)->first()) ? "{$a->user?->name} {$a->maskedNumber()} ({$a->currency})" : null),
                    Select::make('direction')->required()
                        ->options(collect(LedgerDirection::cases())->mapWithKeys(fn (LedgerDirection $d): array => [$d->value => $d->label()])),
                    TextInput::make('amount')->required()->numeric()->minValue(0.01),
                    TextInput::make('description')->maxLength(255),
                    Textarea::make('reason')->required()->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $minor = Money::toMinor((string) $data['amount']);

                    if ($minor === null) {
                        Notification::make()->title('Enter a valid amount.')->danger()->send();

                        return;
                    }

                    $account = Account::query()->findOrFail($data['account_id']);

                    if (! $this->attempt(fn () => app(LedgerService::class)->postAdjustment(
                        $account, LedgerDirection::from($data['direction']), $minor, $data['description'] ?? null, auth()->user(), $data['reason'],
                    ))) {
                        return;
                    }

                    Notification::make()->title('Adjustment posted')->success()->send();
                }),
        ];
    }
}
