<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountType;
use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    /**
     * Customers matching the search (all of them, capped, when empty) keyed by id.
     *
     * @return array<int, string>
     */
    private static function customerOptions(string $search = ''): array
    {
        return User::query()->role(RoleName::Customer->value)
            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->limit(50)->pluck('name', 'id')->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Customer')->required()->searchable()
                ->preload()
                ->options(fn (): array => self::customerOptions())
                ->getSearchResultsUsing(fn (string $search): array => self::customerOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name'))
                ->disabledOn('edit')
                ->dehydrated(),
            Select::make('account_type')->label('Account type')->required()
                ->options(collect(AccountType::cases())->mapWithKeys(fn (AccountType $t): array => [$t->value => $t->label()])),
            TextInput::make('currency')->required()->length(3)->placeholder('USD')->disabledOn('edit')->dehydrated(),
            Textarea::make('reason')->required()->maxLength(500)->helperText('Recorded in the audit log.')->dehydrated(),
        ]);
    }
}
