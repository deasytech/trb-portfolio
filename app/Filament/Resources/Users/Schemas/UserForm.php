<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(32),
            TextInput::make('country')->label('Country code')->minLength(2)->maxLength(2)->placeholder('US'),
            Textarea::make('reason')->required()->maxLength(500)->helperText('Recorded in the audit log.')->dehydrated(),
        ]);
    }
}
