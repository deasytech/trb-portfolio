<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_reference')->label('Reference')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('country')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->dateTime()->sortable()->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('id', 'desc');
    }
}
