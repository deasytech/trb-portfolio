<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Models\Transfer;
use App\Services\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('type')->formatStateUsing(fn (TransferType $state): string => $state->label()),
                TextColumn::make('amount_minor')->label('Amount')
                    ->formatStateUsing(fn (int $state, Transfer $record): string => Money::format($state, $record->currency))
                    ->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (TransferStatus $state): string => $state->label())
                    ->color(fn (TransferStatus $state): string => $state->color()),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(TransferStatus::cases())->mapWithKeys(fn (TransferStatus $s) => [$s->value => $s->label()])),
                SelectFilter::make('type')->options(collect(TransferType::cases())->mapWithKeys(fn (TransferType $t) => [$t->value => $t->label()])),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }
}
