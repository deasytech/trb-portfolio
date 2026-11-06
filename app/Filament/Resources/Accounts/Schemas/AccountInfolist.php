<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Services\AccountBalanceCalculator;
use App\Services\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $balance = fn (Account $record) => app(AccountBalanceCalculator::class)->forAccount($record);

        return $schema->components([
            TextEntry::make('user.name')->label('Customer'),
            TextEntry::make('account_number_last_four')->label('Account')->formatStateUsing(fn (string $state): string => '•••• '.$state),
            TextEntry::make('account_type')->formatStateUsing(fn (AccountType $state): string => $state->label()),
            TextEntry::make('currency'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (AccountStatus $state): string => $state->label()),
            TextEntry::make('ledger_balance')->label('Ledger balance')
                ->state(fn (Account $record): string => Money::format($balance($record)->ledgerMinor, $record->currency)),
            TextEntry::make('held_balance')->label('On hold')
                ->state(fn (Account $record): string => Money::format($balance($record)->heldMinor, $record->currency)),
            TextEntry::make('available_balance')->label('Available')
                ->state(fn (Account $record): string => Money::format($balance($record)->availableMinor(), $record->currency)),
            TextEntry::make('opened_at')->date(),
        ]);
    }
}
