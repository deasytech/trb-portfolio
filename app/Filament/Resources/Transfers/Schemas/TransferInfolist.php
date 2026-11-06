<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Models\Transfer;
use App\Services\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference'),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('type')->formatStateUsing(fn (TransferType $state): string => $state->label()),
            TextEntry::make('status')->badge()->formatStateUsing(fn (TransferStatus $state): string => $state->label())->color(fn (TransferStatus $state): string => $state->color()),
            TextEntry::make('amount_minor')->label('Amount')->formatStateUsing(fn (int $state, Transfer $record): string => Money::format($state, $record->currency)),
            TextEntry::make('beneficiary_name')->placeholder('-'),
            TextEntry::make('description')->placeholder('-'),
            TextEntry::make('rejection_reason')->placeholder('-'),
            TextEntry::make('submitted_at')->dateTime()->placeholder('-'),
            TextEntry::make('approved_at')->dateTime()->placeholder('-'),
            TextEntry::make('completed_at')->dateTime()->placeholder('-'),
        ]);
    }
}
