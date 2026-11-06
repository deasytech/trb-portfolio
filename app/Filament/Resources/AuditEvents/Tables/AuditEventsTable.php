<?php

namespace App\Filament\Resources\AuditEvents\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('event')->searchable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('System'),
                TextColumn::make('reason')->limit(60)->placeholder('-'),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }
}
