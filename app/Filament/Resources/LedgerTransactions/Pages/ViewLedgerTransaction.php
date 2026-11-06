<?php

namespace App\Filament\Resources\LedgerTransactions\Pages;

use App\Enums\LedgerTransactionType;
use App\Enums\PermissionName;
use App\Filament\Concerns\NotifiesServiceErrors;
use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use App\Models\LedgerTransaction;
use App\Services\LedgerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<LedgerTransaction>
 */
class ViewLedgerTransaction extends ViewRecord
{
    use NotifiesServiceErrors;

    protected static string $resource = LedgerTransactionResource::class;

    /**
     * Ledger rows are immutable, so removing one posts a compensating reversal.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reverse')
                ->label('Reverse (remove) entry')
                ->color('danger')
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->required()->maxLength(500)])
                ->visible(function (): bool {
                    /** @var LedgerTransaction $entry */
                    $entry = $this->getRecord();

                    return auth()->user()->can(PermissionName::AdjustBalances->value)
                        && $entry->type !== LedgerTransactionType::Reversal
                        && $entry->related_type === null
                        && ! LedgerTransaction::query()->where('reverses_id', $entry->id)->exists();
                })
                ->action(function (array $data): void {
                    /** @var LedgerTransaction $entry */
                    $entry = $this->getRecord();

                    if (! $this->attempt(fn () => app(LedgerService::class)->reverseEntry($entry, auth()->user(), $data['reason']))) {
                        return;
                    }

                    Notification::make()->title('Entry reversed')->success()->send();
                    $this->redirect(LedgerTransactionResource::getUrl('index'));
                }),
        ];
    }
}
