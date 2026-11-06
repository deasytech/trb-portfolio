<?php

namespace App\Filament\Resources\LedgerTransactions;

use App\Enums\PermissionName;
use App\Filament\Concerns\ReadOnlyStaffResource;
use App\Filament\Resources\LedgerTransactions\Pages\ListLedgerTransactions;
use App\Filament\Resources\LedgerTransactions\Pages\ViewLedgerTransaction;
use App\Filament\Resources\LedgerTransactions\Schemas\LedgerTransactionInfolist;
use App\Filament\Resources\LedgerTransactions\Tables\LedgerTransactionsTable;
use App\Models\LedgerTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LedgerTransactionResource extends Resource
{
    use ReadOnlyStaffResource;

    protected static ?string $model = LedgerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Banking';

    protected static ?int $navigationSort = 4;

    protected static function viewPermission(): PermissionName
    {
        return PermissionName::ViewLedger;
    }

    public static function infolist(Schema $schema): Schema
    {
        return LedgerTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LedgerTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLedgerTransactions::route('/'),
            'view' => ViewLedgerTransaction::route('/{record}'),
        ];
    }
}
