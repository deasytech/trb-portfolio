<?php

use App\Enums\AccountStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\FundsHolds\Pages\ListFundsHolds;
use App\Filament\Resources\FundsHolds\Pages\ViewFundsHold;
use App\Filament\Resources\LedgerTransactions\Pages\ListLedgerTransactions;
use App\Filament\Resources\LedgerTransactions\Pages\ViewLedgerTransaction;
use App\Filament\Resources\Staff\Pages\ViewStaff;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\Pages\ViewTransfer;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->administrator()->create();
    $this->super = User::factory()->superAdministrator()->create();
    $this->customer = User::factory()->customer()->create();
});

test('staff can create, edit and delete a customer with an audit trail', function () {
    Notification::fake();

    Livewire::actingAs($this->admin)->test(CreateUser::class)
        ->fillForm(['name' => 'Ada Obi', 'email' => 'ada@example.com', 'reason' => 'Onboarding'])
        ->call('create')
        ->assertHasNoFormErrors();

    $ada = User::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($ada->hasRole('customer'))->toBeTrue();

    Livewire::actingAs($this->admin)->test(EditUser::class, ['record' => $ada->getRouteKey()])
        ->fillForm(['name' => 'Ada Okafor', 'email' => 'ada@example.com', 'reason' => 'Name change'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($ada->refresh()->name)->toBe('Ada Okafor');

    Livewire::actingAs($this->admin)->test(ViewUser::class, ['record' => $ada->getRouteKey()])
        ->callAction('delete', ['reason' => 'Duplicate']);

    expect(User::query()->whereKey($ada->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->whereIn('event', ['customer.created', 'customer.updated', 'customer.deleted'])->count())->toBe(3);
});

test('a customer with accounts cannot be deleted', function () {
    Account::factory()->for($this->customer)->create();

    Livewire::actingAs($this->admin)->test(ViewUser::class, ['record' => $this->customer->getRouteKey()])
        ->callAction('delete', ['reason' => 'Cleanup']);

    expect($this->customer->fresh())->not->toBeNull();
});

test('staff can open, edit and delete an unused account', function () {
    Livewire::actingAs($this->admin)->test(CreateAccount::class)
        ->fillForm(['user_id' => $this->customer->id, 'account_type' => 'checking', 'currency' => 'usd', 'reason' => 'Requested'])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = Account::query()->where('user_id', $this->customer->id)->firstOrFail();
    expect($account->status)->toBe(AccountStatus::Active)->and($account->currency)->toBe('USD');

    Livewire::actingAs($this->admin)->test(EditAccount::class, ['record' => $account->getRouteKey()])
        ->fillForm(['account_type' => 'savings', 'reason' => 'Upgrade'])
        ->call('save');
    expect($account->refresh()->account_type->value)->toBe('savings');

    Livewire::actingAs($this->admin)->test(ViewAccount::class, ['record' => $account->getRouteKey()])
        ->callAction('delete', ['reason' => 'Opened in error']);
    expect(Account::query()->whereKey($account->id)->exists())->toBeFalse();
});

test('an account with ledger history cannot be deleted', function () {
    $account = Account::factory()->for($this->customer)->create();
    LedgerTransaction::factory()->for($account)->credit(1000)->create();

    Livewire::actingAs($this->admin)->test(ViewAccount::class, ['record' => $account->getRouteKey()])
        ->callAction('delete', ['reason' => 'Cleanup']);

    expect(Account::query()->whereKey($account->id)->exists())->toBeTrue();
});

test('only super administrators can edit and delete staff', function () {
    $staff = User::factory()->administrator()->create();

    $this->actingAs($this->admin)->get('/admin/staff/'.$staff->getRouteKey())->assertForbidden();

    Livewire::actingAs($this->super)->test(ViewStaff::class, ['record' => $staff->getRouteKey()])
        ->callAction('editDetails', ['name' => 'Renamed', 'email' => $staff->email, 'reason' => 'Fix']);
    expect($staff->refresh()->name)->toBe('Renamed');

    Livewire::actingAs($this->super)->test(ViewStaff::class, ['record' => $staff->getRouteKey()])
        ->callAction('deleteStaff', ['reason' => 'Left']);

    expect(User::query()->whereKey($staff->id)->exists())->toBeFalse();
});

test('a hold can be edited and removed by releasing it', function () {
    $account = Account::factory()->for($this->customer)->create();
    $hold = FundsHold::factory()->create(['account_id' => $account->id, 'customer_id' => $this->customer->id]);

    Livewire::actingAs($this->admin)->test(ViewFundsHold::class, ['record' => $hold->getRouteKey()])
        ->callAction('edit', ['hold_reason' => 'Updated reason', 'expires_at' => null, 'reason' => 'Clarify']);
    expect($hold->refresh()->reason)->toBe('Updated reason');

    Livewire::actingAs($this->admin)->test(ViewFundsHold::class, ['record' => $hold->getRouteKey()])
        ->callAction('release', ['reason' => 'Done']);
    expect($hold->refresh()->remaining_amount_minor)->toBe(0);
});

test('staff can create, edit and cancel a transfer for a customer', function () {
    $account = Account::factory()->for($this->customer)->create();
    LedgerTransaction::factory()->for($account)->credit(100_000)->create();

    Livewire::actingAs($this->admin)->test(ListTransfers::class)
        ->callAction('createTransfer', [
            'customer_id' => $this->customer->id,
            'type' => TransferType::IncomingRequest->value,
            'destination_account_id' => $account->id,
            'beneficiary_name' => 'Sender',
            'amount' => '25.00',
        ]);

    $transfer = Transfer::query()->firstOrFail();
    expect($transfer->status)->toBe(TransferStatus::Pending)->and($transfer->created_by)->toBe($this->admin->id);

    Livewire::actingAs($this->admin)->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->callAction('edit', ['description' => 'Fixed', 'beneficiary_name' => 'Sender Ltd', 'reason' => 'Typo']);
    expect($transfer->refresh()->description)->toBe('Fixed');

    Livewire::actingAs($this->admin)->test(ViewTransfer::class, ['record' => $transfer->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Requested']);
    expect($transfer->refresh()->status)->toBe(TransferStatus::Cancelled);
});

test('super administrators can post an adjustment and reverse it', function () {
    $account = Account::factory()->for($this->customer)->create();

    Livewire::actingAs($this->super)->test(ListLedgerTransactions::class)
        ->callAction('postAdjustment', ['account_id' => $account->id, 'direction' => 'credit', 'amount' => '10.00', 'reason' => 'Goodwill']);

    $entry = LedgerTransaction::query()->firstOrFail();
    expect($entry->type)->toBe(LedgerTransactionType::Adjustment)->and($entry->direction)->toBe(LedgerDirection::Credit);

    Livewire::actingAs($this->super)->test(ViewLedgerTransaction::class, ['record' => $entry->getRouteKey()])
        ->callAction('reverse', ['reason' => 'Mistake']);

    expect(LedgerTransaction::query()->where('reverses_id', $entry->id)->exists())->toBeTrue();
});

test('adjustments are hidden from administrators and cannot overdraw an account', function () {
    $account = Account::factory()->for($this->customer)->create();

    Livewire::actingAs($this->admin)->test(ListLedgerTransactions::class)->assertActionHidden('postAdjustment');

    Livewire::actingAs($this->super)->test(ListLedgerTransactions::class)
        ->callAction('postAdjustment', ['account_id' => $account->id, 'direction' => 'debit', 'amount' => '5.00', 'reason' => 'Fee']);

    expect(LedgerTransaction::query()->count())->toBe(0);
});

test('searchable account and customer selects preload their options', function () {
    $account = Account::factory()->for($this->customer)->create();

    $page = Livewire::actingAs($this->admin)->test(CreateAccount::class);
    expect($page->instance()->form->getComponent('user_id')->getOptions())->toHaveKey($this->customer->id);

    $holds = Livewire::actingAs($this->admin)->test(ListFundsHolds::class)->mountAction('place');
    $schema = $holds->instance()->getMountedAction()->getSchema(Schema::make($holds->instance()));
    $select = collect($schema->getComponents())->first(fn ($component) => $component->getName() === 'account_id');
    expect($select->getOptions())->toHaveKey($account->id);
});
