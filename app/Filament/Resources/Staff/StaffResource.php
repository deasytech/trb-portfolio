<?php

namespace App\Filament\Resources\Staff;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Filament\Concerns\ReadOnlyStaffResource;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Pages\ViewStaff;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StaffResource extends Resource
{
    use ReadOnlyStaffResource;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static ?string $navigationLabel = 'Staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    /**
     * Staff are visible only to users who can manage staff.
     */
    protected static function viewPermission(): PermissionName
    {
        return PermissionName::ManageStaff;
    }

    /**
     * Limit the resource to users holding a staff role.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', RoleName::staffValues()))
            ->with('roles');
    }

    /**
     * Read-only detail view.
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('email'),
            TextEntry::make('roles.name')->label('Role')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
            TextEntry::make('status')->badge()->formatStateUsing(fn (UserStatus $state): string => $state->label()),
            TextEntry::make('last_login_at')->dateTime()->placeholder('Never'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }

    /**
     * Staff list table.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->label('Role')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'danger'),
                TextColumn::make('last_login_at')->dateTime()->placeholder('Never')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('name');
    }

    /**
     * Index and read-only view pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'view' => ViewStaff::route('/{record}'),
        ];
    }
}
