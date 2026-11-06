<?php

use App\Enums\RoleName;
use App\Enums\TransferType;
use App\Enums\UserStatus;
use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Pages\ViewStaff;
use App\Filament\Widgets\AdminOverview;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\LedgerTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StaffService;
use App\Services\TransferService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->super = User::factory()->superAdministrator()->create();
    $this->admin = User::factory()->administrator()->create();
});

test('the overview widget groups money by currency', function () {
    $usd = Account::factory()->for(User::factory()->customer()->create())->create(['currency' => 'USD']);
    $eur = Account::factory()->for(User::factory()->customer()->create())->create(['currency' => 'EUR']);
    LedgerTransaction::factory()->for($usd)->credit(5000)->create(['currency' => 'USD']);
    LedgerTransaction::factory()->for($eur)->credit(700)->create(['currency' => 'EUR']);

    Livewire::actingAs($this->admin)->test(AdminOverview::class)
        ->assertSee('Ledger balance (USD)')
        ->assertSee('USD 50.00')
        ->assertSee('Ledger balance (EUR)')
        ->assertSee('EUR 7.00');
});

test('only super administrators can see the staff area', function () {
    Livewire::actingAs($this->super)->test(ListStaff::class)
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([User::factory()->customer()->create()]);

    $this->actingAs($this->admin)->get('/admin/staff')->assertForbidden();
});

test('a super administrator changes a role with a reason and it is audited', function () {
    app(StaffService::class)->changeRole($this->admin, RoleName::SuperAdministrator, $this->super, 'Promoted for cover');

    expect($this->admin->fresh()->hasRole(RoleName::SuperAdministrator->value))->toBeTrue()
        ->and(AuditEvent::where('event', 'staff.role_changed')->where('reason', 'Promoted for cover')->exists())->toBeTrue();
});

test('role and status changes need a reason, the permission, and another person', function () {
    $service = app(StaffService::class);

    expect(fn () => $service->changeRole($this->admin, RoleName::SuperAdministrator, $this->super, ' '))->toThrow(ValidationException::class)
        ->and(fn () => $service->changeRole($this->admin, RoleName::SuperAdministrator, $this->admin, 'self'))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->setStatus($this->super, UserStatus::Suspended, $this->super, 'self'))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->setStatus($this->admin, UserStatus::Suspended, User::factory()->administrator()->create(), 'nope'))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->setStatus(User::factory()->customer()->create(), UserStatus::Suspended, $this->super, 'wrong kind'))->toThrow(AuthorizationException::class);
});

test('a suspended super administrator can no longer manage staff', function () {
    $service = app(StaffService::class);
    $second = User::factory()->superAdministrator()->create();

    $service->setStatus($second, UserStatus::Suspended, $this->super, 'Left the team');

    expect(fn () => $service->changeRole($this->admin, RoleName::SuperAdministrator, $second->fresh(), 'x'))->toThrow(AuthorizationException::class);
});

test('the staff view page can deactivate a staff member', function () {
    Livewire::actingAs($this->super)->test(ViewStaff::class, ['record' => $this->admin->getRouteKey()])
        ->callAction('deactivate', ['reason' => 'Left'])
        ->assertNotified();

    expect($this->admin->fresh()->status)->toBe(UserStatus::Suspended);
});

test('settings fall back to config until overridden', function () {
    $settings = app(SettingsService::class);

    expect($settings->transferMaxMinor())->toBe(config('portfolio.transfers.max_minor'))
        ->and($settings->transfersEnabled())->toBeTrue();

    $settings->update([SettingsService::TRANSFER_MAX => 5000], $this->super, 'Tighten limit');

    expect($settings->transferMaxMinor())->toBe(5000)
        ->and(AuditEvent::where('event', 'settings.updated')->first()->metadata)->toMatchArray(['key' => 'transfer_max_minor', 'to' => 5000]);
});

test('financial limits need the elevated permission but general settings do not', function () {
    $settings = app(SettingsService::class);

    $settings->update([SettingsService::SUPPORT_EMAIL => 'help@example.com'], $this->admin, 'Update contact');
    expect($settings->get(SettingsService::SUPPORT_EMAIL))->toBe('help@example.com');

    expect(fn () => $settings->update([SettingsService::TRANSFER_MAX => 1], $this->admin, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => $settings->update([SettingsService::TRANSFERS_ENABLED => false], $this->admin, 'x'))->toThrow(AuthorizationException::class)
        ->and(Setting::where('key', SettingsService::TRANSFER_MAX)->exists())->toBeFalse();
});

test('settings reject invalid values, unknown keys and a missing reason', function () {
    $settings = app(SettingsService::class);

    expect(fn () => $settings->update([SettingsService::TRANSFER_MIN => 0], $this->super, 'x'))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update([SettingsService::TRANSFER_MIN => 9_999_999_999], $this->super, 'x'))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update([SettingsService::DEFAULT_CURRENCY => 'dollars'], $this->super, 'x'))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update(['database_password' => 'x'], $this->super, 'x'))->toThrow(ValidationException::class)
        ->and(fn () => $settings->update([SettingsService::SUPPORT_EMAIL => 'a@example.com'], $this->super, ''))->toThrow(ValidationException::class);
});

test('disabling transfers blocks new customer requests', function () {
    $customer = User::factory()->customer()->create();
    $account = Account::factory()->for($customer)->create(['currency' => 'USD']);
    LedgerTransaction::factory()->for($account)->credit(100_000)->create(['currency' => 'USD']);
    $data = ['source_account_id' => $account->id, 'beneficiary_name' => 'Bob', 'destination_identifier' => 'GB00', 'amount_minor' => 1000];

    app(SettingsService::class)->update([SettingsService::TRANSFERS_ENABLED => false], $this->super, 'Maintenance');

    expect(fn () => app(TransferService::class)->request($customer, TransferType::OutgoingRequest, $data, 'key-1'))->toThrow(ValidationException::class);

    app(SettingsService::class)->update([SettingsService::TRANSFERS_ENABLED => true], $this->super, 'Done');

    expect(app(TransferService::class)->request($customer, TransferType::OutgoingRequest, $data, 'key-1')->exists)->toBeTrue();
});

test('the settings page saves general settings for administrators and hides limits from them', function () {
    Livewire::actingAs($this->admin)->test(SystemSettings::class)
        ->fillForm([SettingsService::SUPPORT_PHONE => '+1 555 0100'])
        ->callAction('save', ['reason' => 'Updated phone'])
        ->assertNotified();

    expect(app(SettingsService::class)->get(SettingsService::SUPPORT_PHONE))->toBe('+1 555 0100');

    $this->actingAs(User::factory()->customer()->create())->get('/admin/system-settings')->assertForbidden();
});

test('a super administrator can create staff who receive a reset link and an audit trail', function () {
    Notification::fake();

    $staff = app(StaffService::class)->create('New Admin', 'New.Admin@Example.com', RoleName::Administrator, $this->super, 'Joined ops');

    expect($staff->email)->toBe('new.admin@example.com')
        ->and($staff->hasRole(RoleName::Administrator->value))->toBeTrue()
        ->and($staff->isActive())->toBeTrue()
        ->and($staff->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and(AuditEvent::where('event', 'staff.created')->where('reason', 'Joined ops')->exists())->toBeTrue();

    Notification::assertSentTo($staff, ResetPassword::class);
});

test('staff creation is restricted and validated', function () {
    $service = app(StaffService::class);

    expect(fn () => $service->create('X', 'x@example.com', RoleName::Administrator, $this->admin, 'r'))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->create('X', 'x@example.com', RoleName::Customer, $this->super, 'r'))->toThrow(ValidationException::class)
        ->and(fn () => $service->create('X', 'not-an-email', RoleName::Administrator, $this->super, 'r'))->toThrow(ValidationException::class)
        ->and(fn () => $service->create('X', strtoupper($this->admin->email), RoleName::Administrator, $this->super, 'r'))->toThrow(ValidationException::class)
        ->and(fn () => $service->create('X', 'x@example.com', RoleName::Administrator, $this->super, ' '))->toThrow(ValidationException::class);
});

test('the staff list page adds a staff member', function () {
    Notification::fake();

    Livewire::actingAs($this->super)->test(ListStaff::class)
        ->callAction('createStaff', ['name' => 'Page Admin', 'email' => 'page@example.com', 'role' => RoleName::Administrator->value, 'reason' => 'New hire'])
        ->assertNotified();

    expect(User::where('email', 'page@example.com')->exists())->toBeTrue();
});

test('customers see the maintenance notice and it is escaped', function () {
    app(SettingsService::class)->update([SettingsService::MAINTENANCE_NOTICE => 'Down <b>Sunday</b>'], $this->admin, 'Notice');
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('customer.dashboard'))
        ->assertSee('data-test="maintenance-notice"', false)
        ->assertSee('Down &lt;b&gt;Sunday&lt;/b&gt;', false);

    app(SettingsService::class)->update([SettingsService::MAINTENANCE_NOTICE => ''], $this->admin, 'Clear');

    $this->actingAs($customer)->get(route('customer.dashboard'))->assertDontSee('maintenance-notice');
});

test('the database seeder assigns permissions to roles even with model events disabled', function () {
    DB::table('role_has_permissions')->delete();
    DB::table('roles')->delete();
    DB::table('permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(DatabaseSeeder::class);

    expect(Role::findByName(RoleName::SuperAdministrator->value, 'web')->permissions)->not->toBeEmpty()
        ->and(Role::findByName(RoleName::Administrator->value, 'web')->permissions)->not->toBeEmpty();
});
