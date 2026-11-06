<?php

namespace App\Filament\Resources\FundsHolds\Schemas;

use App\Enums\HoldStatus;
use App\Models\FundsHold;
use App\Services\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FundsHoldInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference'),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (HoldStatus $state): string => $state->label())->color(fn (HoldStatus $state): string => $state->color()),
            TextEntry::make('amount_minor')->label('Original amount')->formatStateUsing(fn (int $state, FundsHold $record): string => Money::format($state, $record->currency)),
            TextEntry::make('remaining_amount_minor')->label('Remaining')->formatStateUsing(fn (int $state, FundsHold $record): string => Money::format($state, $record->currency)),
            TextEntry::make('reason'),
            TextEntry::make('release_reason')->placeholder('-'),
            TextEntry::make('expires_at')->dateTime()->placeholder('None'),
            TextEntry::make('released_at')->dateTime()->placeholder('-'),
        ]);
    }
}
