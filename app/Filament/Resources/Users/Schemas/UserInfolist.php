<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('customer_reference')->label('Reference'),
            TextEntry::make('name'),
            TextEntry::make('email'),
            TextEntry::make('phone')->placeholder('-'),
            TextEntry::make('country')->placeholder('-'),
            TextEntry::make('status')->badge()->formatStateUsing(fn (UserStatus $state): string => $state->label()),
            TextEntry::make('email_verified_at')->dateTime()->placeholder('Unverified'),
            TextEntry::make('last_login_at')->dateTime()->placeholder('Never'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
