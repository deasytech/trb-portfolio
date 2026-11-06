<?php

use App\Enums\HoldStatus;
use App\Models\Account;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use Livewire\Livewire;

function customerWithAccount(): array
{
    $user = User::factory()->customer()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    return [$user, $account];
}

test('dashboard cards are calculated from the ledger, holds and open transfers', function () {
    [$user, $account] = customerWithAccount();
    LedgerTransaction::factory()->credit(100_000)->create(['account_id' => $account->id]);
    FundsHold::factory()->create(['account_id' => $account->id, 'amount_minor' => 20_000, 'remaining_amount_minor' => 20_000]);
    Transfer::factory()->create(['customer_id' => $user->id, 'source_account_id' => $account->id, 'amount_minor' => 5_000]);
    Transfer::factory()->completed()->create(['customer_id' => $user->id, 'source_account_id' => $account->id, 'amount_minor' => 9_999]);

    $this->actingAs($user);

    Livewire::test('pages::customer.dashboard')
        ->assertSee('USD 800.00')   // available
        ->assertSee('USD 200.00')   // held
        ->assertSee('USD 1,000.00') // assets
        ->assertSee('USD 50.00')    // pending (completed transfer excluded)
        ->assertSee('1 request');
});

test('dashboard shows empty states for a new customer', function () {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test('pages::customer.dashboard')
        ->assertSee('No transfers yet.')
        ->assertSee('No funds are currently on hold.');
});

test('account summary masks numbers and never shows other customers accounts', function () {
    [$user, $account] = customerWithAccount();
    Account::factory()->create(['account_number' => '5555555555']);

    $this->actingAs($user)->get(route('customer.account-summary'))
        ->assertOk()
        ->assertSee($account->maskedNumber())
        ->assertDontSee($account->account_number)
        ->assertDontSee('5555');
});

test('a customer can view their own transfer but not another customers', function () {
    [$user] = customerWithAccount();
    $own = Transfer::factory()->create(['customer_id' => $user->id]);
    $foreign = Transfer::factory()->create();

    $this->actingAs($user);

    $this->get(route('customer.transfers.show', $own))->assertOk()->assertSee($own->reference);
    $this->get(route('customer.transfers.show', $foreign))->assertNotFound();
});

test('transfers list only shows the customers own transfers', function () {
    [$user] = customerWithAccount();
    $own = Transfer::factory()->create(['customer_id' => $user->id]);
    $foreign = Transfer::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::customer.transfers')
        ->assertSee($own->reference)
        ->assertDontSee($foreign->reference);
});

test('history filters server side and is scoped to the customer', function () {
    [$user, $account] = customerWithAccount();
    LedgerTransaction::factory()->credit(1_000)->create(['account_id' => $account->id, 'reference' => 'TXN-WANTED']);
    LedgerTransaction::factory()->credit(1_000)->create(['account_id' => $account->id, 'reference' => 'TXN-OTHER']);
    LedgerTransaction::factory()->create(['reference' => 'TXN-FOREIGN']);

    $this->actingAs($user);

    Livewire::test('pages::customer.history')
        ->assertSee('TXN-WANTED')->assertSee('TXN-OTHER')->assertDontSee('TXN-FOREIGN')
        ->set('search', 'WANTED')
        ->assertSee('TXN-WANTED')->assertDontSee('TXN-OTHER')
        ->set('search', '')
        ->set('minAmount', '20')
        ->assertSee('No transactions found');
});

test('history refuses to open another customers transaction', function () {
    [$user] = customerWithAccount();
    $foreign = LedgerTransaction::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::customer.history')
        ->call('select', $foreign->id)
        ->assertSet('selectedId', null);
});

test('history validates the date range', function () {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test('pages::customer.history')
        ->set('from', '2026-02-01')
        ->set('to', '2026-01-01')
        ->assertHasErrors(['to']);
});

test('holds page lists holds with active total and keeps released history', function () {
    [$user, $account] = customerWithAccount();
    FundsHold::factory()->create(['account_id' => $account->id, 'reason' => 'Transfer Review', 'amount_minor' => 30_000, 'remaining_amount_minor' => 30_000]);
    FundsHold::factory()->released()->create(['account_id' => $account->id, 'reason' => 'Old hold']);
    FundsHold::factory()->create(['reason' => 'Someone elses hold']);

    $this->actingAs($user);

    Livewire::test('pages::customer.holds')
        ->assertSee('USD 300.00')
        ->assertSee('Transfer Review')
        ->assertSee('Old hold')
        ->assertSee(HoldStatus::Released->label())
        ->assertDontSee('Someone elses hold');
});

test('profile saves phone and country and validates them', function () {
    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('phone', 'not a phone!')
        ->set('country', 'USA')
        ->call('updateProfileInformation')
        ->assertHasErrors(['phone', 'country']);

    Livewire::test('pages::settings.profile')
        ->set('phone', '+1 555 123 4567')
        ->set('country', 'us')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->phone)->toBe('+1 555 123 4567')->and($user->country)->toBe('US');
});

test('settings persist theme, timezone and notification preferences', function () {
    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.appearance')
        ->set('theme', 'dark')
        ->set('timezone', 'Africa/Lagos')
        ->set('emailNotifications', false)
        ->call('save')
        ->assertHasNoErrors();

    $preference = $user->refresh()->preference;
    expect($preference->theme->value)->toBe('dark')
        ->and($preference->timezone)->toBe('Africa/Lagos')
        ->and($preference->notification_preferences)->toBe(['email' => false, 'in_app' => true]);

    Livewire::test('pages::settings.appearance')
        ->set('theme', 'neon')
        ->set('timezone', 'Mars/Base')
        ->call('save')
        ->assertHasErrors(['theme', 'timezone']);
});
