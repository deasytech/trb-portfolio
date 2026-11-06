<?php

namespace App\Filament\Resources\LedgerTransactions\Schemas;

use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use App\Services\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LedgerTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference'),
            TextEntry::make('account.user.name')->label('Customer'),
            TextEntry::make('type')->formatStateUsing(fn (LedgerTransactionType $state): string => $state->label()),
            TextEntry::make('direction')->formatStateUsing(fn (LedgerDirection $state): string => $state->label()),
            TextEntry::make('amount_minor')->label('Amount')->formatStateUsing(fn (int $state, LedgerTransaction $record): string => Money::format($state, $record->currency)),
            TextEntry::make('description')->placeholder('-'),
            TextEntry::make('reverses.reference')->label('Reverses')->placeholder('-'),
            TextEntry::make('posted_at')->dateTime(),
        ]);
    }
}
