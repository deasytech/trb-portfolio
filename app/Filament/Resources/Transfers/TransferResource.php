<?php

namespace App\Filament\Resources\Transfers;

use App\Enums\PermissionName;
use App\Filament\Concerns\ReadOnlyStaffResource;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\Pages\ViewTransfer;
use App\Filament\Resources\Transfers\Schemas\TransferInfolist;
use App\Filament\Resources\Transfers\Tables\TransfersTable;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransferResource extends Resource
{
    use ReadOnlyStaffResource;

    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static function viewPermission(): PermissionName
    {
        return PermissionName::ViewTransfers;
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransfers::route('/'),
            'view' => ViewTransfer::route('/{record}'),
        ];
    }
}
