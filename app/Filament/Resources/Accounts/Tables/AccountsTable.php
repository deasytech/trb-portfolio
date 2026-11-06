<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('account_number_last_four')->label('Account')->formatStateUsing(fn (string $state): string => '•••• '.$state)->searchable(),
                TextColumn::make('account_type')->formatStateUsing(fn (AccountType $state): string => $state->label()),
                TextColumn::make('currency'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (AccountStatus $state): string => $state->label())
                    ->color(fn (AccountStatus $state): string => $state->color()),
                TextColumn::make('opened_at')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $s) => [$s->value => $s->label()])),
                SelectFilter::make('currency')->options(fn (): array => Account::query()->distinct()->pluck('currency', 'currency')->all()),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('id', 'desc');
    }
}
