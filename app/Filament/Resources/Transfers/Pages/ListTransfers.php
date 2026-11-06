<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Enums\TransferType;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Account;
use App\Models\User;
use App\Services\Money;
use App\Services\TransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

class ListTransfers extends ListRecords
{
    use NotifiesServiceErrors;

    protected static string $resource = TransferResource::class;

    /**
     * The idempotency key of the last submitted request, bound to a hash of its details.
     *
     * @var array{hash?: string, key?: string}
     */
    public array $submission = [];

    /**
     * Raise a pending transfer request on a customer's behalf.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTransfer')
                ->label('Create transfer')
                ->visible(fn (): bool => auth()->user()->can(PermissionName::ManageTransfers->value))
                ->schema([
                    Select::make('customer_id')->label('Customer')->required()->searchable()->live()
                        ->preload()
                        ->options(fn (): array => AccountForm::customerOptions())
                        ->getSearchResultsUsing(fn (string $search): array => AccountForm::customerOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name')),
                    Select::make('type')->required()->live()
                        ->options(collect(TransferType::cases())->mapWithKeys(fn (TransferType $t): array => [$t->value => $t->label()])),
                    Select::make('source_account_id')->label('Source account')
                        ->options(fn (Get $get): array => self::accountOptions($get('customer_id')))
                        ->visible(fn (Get $get): bool => in_array($get('type'), [TransferType::Internal->value, TransferType::OutgoingRequest->value], true))
                        ->required(fn (Get $get): bool => in_array($get('type'), [TransferType::Internal->value, TransferType::OutgoingRequest->value], true)),
                    Select::make('destination_account_id')->label('Destination account')
                        ->options(fn (Get $get): array => self::accountOptions($get('customer_id')))
                        ->visible(fn (Get $get): bool => in_array($get('type'), [TransferType::Internal->value, TransferType::IncomingRequest->value], true))
                        ->required(fn (Get $get): bool => in_array($get('type'), [TransferType::Internal->value, TransferType::IncomingRequest->value], true)),
                    TextInput::make('beneficiary_name')->label('Recipient / sender name')->maxLength(120)
                        ->visible(fn (Get $get): bool => in_array($get('type'), [TransferType::OutgoingRequest->value, TransferType::IncomingRequest->value], true))
                        ->required(fn (Get $get): bool => $get('type') === TransferType::OutgoingRequest->value),
                    TextInput::make('destination_identifier')->label('Recipient account number')->maxLength(64)
                        ->visible(fn (Get $get): bool => $get('type') === TransferType::OutgoingRequest->value)
                        ->required(fn (Get $get): bool => $get('type') === TransferType::OutgoingRequest->value),
                    TextInput::make('amount')->required()->numeric()->minValue(0.01),
                    TextInput::make('description')->maxLength(255),
                    Textarea::make('reason')->required()->maxLength(500)->helperText('Recorded in the audit log.'),
                ])
                ->action(function (array $data): void {
                    $customer = User::query()->findOrFail($data['customer_id']);
                    $minor = Money::toMinor((string) $data['amount']);

                    if ($minor === null) {
                        Notification::make()->title('Enter a valid amount.')->danger()->send();

                        return;
                    }

                    if (! $this->attempt(fn () => app(TransferService::class)->request($customer, TransferType::from($data['type']), [
                        'source_account_id' => $data['source_account_id'] ?? null,
                        'destination_account_id' => $data['destination_account_id'] ?? null,
                        'beneficiary_name' => $data['beneficiary_name'] ?? null,
                        'destination_identifier' => $data['destination_identifier'] ?? null,
                        'amount_minor' => $minor,
                        'description' => $data['description'] ?? null,
                    ], $this->idempotencyKeyFor($data), auth()->user(), $data['reason']))) {
                        return;
                    }

                    $this->submission = [];

                    Notification::make()->title('Transfer created')->success()->send();
                }),
        ];
    }

    /**
     * Reuse the key while the same details are retried; changed details get a new key.
     *
     * @param  array<string, mixed>  $data
     */
    private function idempotencyKeyFor(array $data): string
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        if (($this->submission['hash'] ?? null) !== $hash) {
            $this->submission = ['hash' => $hash, 'key' => (string) Str::uuid()];
        }

        return $this->submission['key'];
    }

    /**
     * Active accounts of the chosen customer, labelled for selection.
     *
     * @return array<int, string>
     */
    private static function accountOptions(mixed $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return Account::query()->where('user_id', $customerId)->where('status', AccountStatus::Active->value)->get()
            ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->maskedNumber()} ({$a->currency})"])->all();
    }
}
