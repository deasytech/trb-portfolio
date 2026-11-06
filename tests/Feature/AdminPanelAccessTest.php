<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

test('guests are redirected to the admin login, which defers to the Fortify login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');

    $this->get('/admin/login')->assertRedirect(route('login'));
});

test('customers cannot access the admin panel', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('users without a role cannot access the admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

test('administrators and super administrators can access the admin panel', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())->get('/admin')->assertOk();
})->with(['administrator', 'superAdministrator']);

test('suspended staff cannot access the admin panel', function () {
    $this->actingAs(User::factory()->administrator()->suspended()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('the seeder gives least-privilege permissions per role and is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    $customer = Role::findByName(RoleName::Customer->value);
    $admin = Role::findByName(RoleName::Administrator->value);
    $super = Role::findByName(RoleName::SuperAdministrator->value);

    expect($customer->permissions)->toHaveCount(0)
        ->and($admin->hasPermissionTo(PermissionName::ApproveTransfers->value))->toBeTrue()
        ->and($admin->hasPermissionTo(PermissionName::ReverseTransfers->value))->toBeFalse()
        ->and($admin->hasPermissionTo(PermissionName::ManageStaff->value))->toBeFalse()
        ->and($super->permissions)->toHaveCount(count(PermissionName::cases()));
});
