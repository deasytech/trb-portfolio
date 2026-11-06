<?php

namespace App\Filament\Resources\LedgerTransactions\Tables;

use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Models\LedgerTransaction;
use App\Services\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgerTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('account.user.name')->label('Customer'),
                TextColumn::make('type')->formatStateUsing(fn (LedgerTransactionType $state): string => $state->label()),
                TextColumn::make('direction')->formatStateUsing(fn (LedgerDirection $state): string => $state->label()),
                TextColumn::make('amount_minor')->label('Amount')
                    ->formatStateUsing(fn (int $state, LedgerTransaction $record): string => Money::format($state, $record->currency)),
                TextColumn::make('posted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(collect(LedgerTransactionType::cases())->mapWithKeys(fn (LedgerTransactionType $t) => [$t->value => $t->label()])),
                SelectFilter::make('currency')->options(fn (): array => LedgerTransaction::query()->distinct()->pluck('currency', 'currency')->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }
}
