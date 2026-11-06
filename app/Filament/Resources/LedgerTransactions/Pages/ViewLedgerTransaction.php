<?php

namespace App\Filament\Resources\LedgerTransactions\Pages;

use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLedgerTransaction extends ViewRecord
{
    protected static string $resource = LedgerTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
