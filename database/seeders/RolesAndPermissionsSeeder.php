<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent and safe to run in every environment, including production.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = array_map(fn (PermissionName $p): string => $p->value, PermissionName::cases());
        $restricted = array_map(fn (PermissionName $p): string => $p->value, PermissionName::superAdministratorOnly());

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(RoleName::Customer->value, 'web');

        Role::findOrCreate(RoleName::Administrator->value, 'web')
            ->syncPermissions(array_values(array_diff($all, $restricted)));

        Role::findOrCreate(RoleName::SuperAdministrator->value, 'web')
            ->syncPermissions($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
