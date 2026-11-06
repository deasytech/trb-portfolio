<?php

namespace App\Enums;

enum RoleName: string
{
    case Customer = 'customer';
    case Administrator = 'administrator';
    case SuperAdministrator = 'super_administrator';

    /**
     * Roles that grant access to the staff (Filament) panel.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Administrator, self::SuperAdministrator];
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::staff());
    }
}
