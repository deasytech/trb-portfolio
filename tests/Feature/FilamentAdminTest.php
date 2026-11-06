<?php

use App\Enums\AccountStatus;
use App\Enums\HoldStatus;
use App\Enums\TransferStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Filament\Resources\FundsHolds\Pages\ListFundsHolds;
use App\Filament\Resources\FundsHolds\Pages\ViewFundsHold;
use App\Filament\Resources\LedgerTransactions\LedgerTransactionResource;
use App\Filament\Resources\LedgerTransactions\Pages\ListLedgerTransactions;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\Pages\ViewTransfer;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->customer = User::factory()->customer()->create();
    $this->account = Account::factory()->for($this->customer)->create(['currency' => 'USD']);
});

test('administrators can list every staff resource', function (string $page) {
    Livewire::actingAs(User::factory()->administrator()->create())
        ->test($page)
        ->assertOk();
})->with([ListUsers::class, ListAccounts::class, ListTransfers::class, ListFundsHolds::class, ListLedgerTransactions::class, ListAuditEvents::class]);

test('customers cannot reach staff resources', function () {
    $this->actingAs($this->customer)->get('/admin/transfers')->assertForbidden();
    $this->actingAs($this->customer)->get('/admin/ledger-transactions')->assertForbidden();
});

test('the customer list only shows customers', function () {
    $staff = User::factory()->administrator()->create();

    Livewire::actingAs($staff)->test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->customer])
        ->assertCanNotSeeTableRecords([$staff]);
});

test('the ledger resource is read-only', function () {
    $admin = User::factory()->administrator()->create();

    expect(LedgerTransactionResource::canCreate())->toBeFalse();
    Livewire::actingAs($admin)->test(ListLedgerTransactions::class)->assertOk();
    expect(LedgerTransactionResource::canEdit(LedgerTransaction::factory()->for($this->account)->credit(100)->create()))->toBeFalse()
        ->and(LedgerTransactionResource::canDelete(LedgerTransaction::factory()->for($this->account)->credit(100)->create()))->toBeFalse();
});

test('an administrator can approve a pending transfer from the admin panel', function () {
    $admin = User::factory()->administrator()->create();
    $transfer = Transfer::factory()->for($this->customer, 'customer')->create(['status' => TransferStatus::Pending]);

    Livewire::actingAs($admin)->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->callAction('approve');

    expect($transfer->refresh()->status)->toBe(TransferStatus::Approved);
    expect(AuditEvent::where('event', 'transfer.approved')->exists())->toBeTrue();
});

test('rejecting a transfer requires a reason', function () {
    $admin = User::factory()->administrator()->create();
    $transfer = Transfer::factory()->for($this->customer, 'customer')->create(['status' => TransferStatus::Pending]);

    Livewire::actingAs($admin)->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->callAction('reject', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($transfer->refresh()->status)->toBe(TransferStatus::Pending);
});

test('reversal is hidden from administrators and available to super administrators', function () {
    $transfer = Transfer::factory()->for($this->customer, 'customer')->completed()->create();

    Livewire::actingAs(User::factory()->administrator()->create())
        ->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->assertActionHidden('reverse');

    Livewire::actingAs(User::factory()->superAdministrator()->create())
        ->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->assertActionVisible('reverse');
});

test('staff can release an active hold with a reason', function () {
    $admin = User::factory()->administrator()->create();
    $hold = FundsHold::factory()->create([
        'account_id' => $this->account->id,
        'customer_id' => $this->customer->id,
        'status' => HoldStatus::Active,
    ]);

    Livewire::actingAs($admin)->test(ViewFundsHold::class, ['record' => $hold->getRouteKey()])
        ->callAction('release', ['reason' => 'Verified']);

    expect($hold->refresh()->status)->toBe(HoldStatus::Released);
});

test('staff can freeze and unfreeze accounts with an audit trail', function () {
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)->test(ViewAccount::class, ['record' => $this->account->getRouteKey()])
        ->callAction('freeze', ['reason' => 'Suspicious activity']);

    expect($this->account->refresh()->status)->toBe(AccountStatus::Frozen);

    $event = AuditEvent::where('event', 'account.status_changed')->firstOrFail();
    expect($event->reason)->toBe('Suspicious activity')->and($event->actor_id)->toBe($admin->id);
});

test('staff can suspend a customer but a reason is required', function () {
    $admin = User::factory()->administrator()->create();

    Livewire::actingAs($admin)->test(ViewUser::class, ['record' => $this->customer->getRouteKey()])
        ->callAction('suspend', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::actingAs($admin)->test(ViewUser::class, ['record' => $this->customer->getRouteKey()])
        ->callAction('suspend', ['reason' => 'Fraud review']);

    expect($this->customer->refresh()->status)->toBe(UserStatus::Suspended);
});
