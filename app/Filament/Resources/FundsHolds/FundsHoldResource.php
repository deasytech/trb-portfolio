<?php

namespace App\Filament\Resources\FundsHolds;

use App\Enums\PermissionName;
use App\Filament\Concerns\ReadOnlyStaffResource;
use App\Filament\Resources\FundsHolds\Pages\ListFundsHolds;
use App\Filament\Resources\FundsHolds\Pages\ViewFundsHold;
use App\Filament\Resources\FundsHolds\Schemas\FundsHoldInfolist;
use App\Filament\Resources\FundsHolds\Tables\FundsHoldsTable;
use App\Models\FundsHold;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FundsHoldResource extends Resource
{
    use ReadOnlyStaffResource;

    protected static ?string $model = FundsHold::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Banking';

    protected static ?int $navigationSort = 3;

    protected static function viewPermission(): PermissionName
    {
        return PermissionName::ViewHolds;
    }

    public static function infolist(Schema $schema): Schema
    {
        return FundsHoldInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FundsHoldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundsHolds::route('/'),
            'view' => ViewFundsHold::route('/{record}'),
        ];
    }
}
