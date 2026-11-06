<?php

namespace App\Filament\Resources\LedgerTransactions\Pages;

use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListLedgerTransactions extends ListRecords
{
    protected static string $resource = LedgerTransactionResource::class;
}
