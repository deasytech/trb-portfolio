<?php

namespace App\Filament\Resources\FundsHolds\Tables;

use App\Enums\HoldStatus;
use App\Models\FundsHold;
use App\Services\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FundsHoldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('remaining_amount_minor')->label('Remaining')
                    ->formatStateUsing(fn (int $state, FundsHold $record): string => Money::format($state, $record->currency)),
                TextColumn::make('status')->badge()
                    ->state(fn (FundsHold $record): HoldStatus => $record->effectiveStatus())
                    ->formatStateUsing(fn (HoldStatus $state): string => $state->label())
                    ->color(fn (HoldStatus $state): string => $state->color()),
                TextColumn::make('expires_at')->dateTime()->placeholder('None'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(HoldStatus::cases())->mapWithKeys(fn (HoldStatus $s) => [$s->value => $s->label()]))
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        HoldStatus::Active->value => $query->where('status', HoldStatus::Active)->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                        HoldStatus::Expired->value => $query->where(fn (Builder $q) => $q
                            ->where('status', HoldStatus::Expired)
                            ->orWhere(fn (Builder $q) => $q->where('status', HoldStatus::Active)->where('expires_at', '<=', now()))),
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }
}
