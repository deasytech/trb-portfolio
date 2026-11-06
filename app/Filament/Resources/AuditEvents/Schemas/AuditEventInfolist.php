<?php

namespace App\Filament\Resources\AuditEvents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AuditEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('event'),
            TextEntry::make('actor.name')->label('Actor')->placeholder('System'),
            TextEntry::make('subject_type'),
            TextEntry::make('subject_id'),
            TextEntry::make('reason')->placeholder('-'),
            TextEntry::make('metadata')->state(fn ($record): string => json_encode($record->metadata ?? [], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)),
        ]);
    }
}
