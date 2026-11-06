<?php

use App\Enums\TransferStatus;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->account = Account::factory()->create(['user_id' => $this->customer->id]);
    LedgerTransaction::factory()->for($this->account)->credit(100_000)->create();
    $this->actingAs($this->customer);
});

function fillOutgoing($component, string $amount = '250.00')
{
    return $component
        ->set('sourceAccountId', (string) test()->account->id)
        ->set('beneficiaryName', 'Jane Roe')
        ->set('destinationIdentifier', '987654321')
        ->set('outAmount', $amount);
}

test('submitting without reviewing first creates nothing', function () {
    fillOutgoing(Livewire::test('pages::customer.transfers'))->call('submitOut');

    expect(Transfer::count())->toBe(0);
});

test('a transfer out needs explicit confirmation before it is created', function () {
    $component = fillOutgoing(Livewire::test('pages::customer.transfers'))->call('reviewOut');

    expect(Transfer::count())->toBe(0);

    $component->assertSet('confirmingOut', true)->call('submitOut')->assertSet('confirmingOut', false);

    $transfer = Transfer::first();
    expect($transfer->amount_minor)->toBe(25_000)
        ->and($transfer->status)->toBe(TransferStatus::Pending)
        ->and($transfer->customer_id)->toBe($this->customer->id);
});

test('double submitting the confirmation creates one transfer', function () {
    $component = fillOutgoing(Livewire::test('pages::customer.transfers'))->call('reviewOut');
    $key = $component->get('outKey');

    $component->call('submitOut');
    // Replays the same key, as a double-click or retried request would.
    $component->set('outKey', $key);
    fillOutgoing($component)->call('reviewOut')->call('submitOut');

    expect(Transfer::count())->toBe(1);
});

test('invalid amounts show validation errors', function (string $amount) {
    fillOutgoing(Livewire::test('pages::customer.transfers'), $amount)
        ->call('reviewOut')
        ->assertHasErrors('outAmount');
})->with(['abc', '-5', '1.234', '']);

test('an amount beyond the available balance is refused at submission', function () {
    fillOutgoing(Livewire::test('pages::customer.transfers'), '5000.00')
        ->call('reviewOut')->call('submitOut')
        ->assertHasErrors('amount')
        ->assertSet('confirmingOut', false);

    expect(Transfer::count())->toBe(0);
});

test('a deposit notification is created as a pending incoming request', function () {
    Livewire::test('pages::customer.transfers')
        ->set('depositAccountId', (string) $this->account->id)
        ->set('senderName', 'Acme Ltd')
        ->set('expectedAmount', '75.50')
        ->call('reviewIn')->call('submitIn');

    $transfer = Transfer::first();
    expect($transfer->type->value)->toBe('incoming_request')
        ->and($transfer->status)->toBe(TransferStatus::Pending)
        ->and($transfer->destination_account_id)->toBe($this->account->id);
});

test('submissions are rate limited', function () {
    config(['portfolio.transfers.submissions_per_minute' => 1]);

    $component = Livewire::test('pages::customer.transfers');
    fillOutgoing($component, '10.00')->call('reviewOut')->call('submitOut');
    fillOutgoing($component, '11.00')->call('reviewOut')->call('submitOut')->assertHasErrors('rate');

    expect(Transfer::count())->toBe(1);
});

test('a customer can cancel a pending transfer from its details page but not someone elses', function () {
    $transfer = Transfer::factory()->create(['customer_id' => $this->customer->id, 'source_account_id' => $this->account->id]);

    Livewire::test('pages::customer.transfer-details', ['transfer' => $transfer])->call('cancel');
    expect($transfer->refresh()->status)->toBe(TransferStatus::Cancelled);

    $foreign = Transfer::factory()->create();
    $this->get(route('customer.transfers.show', $foreign))->assertNotFound();
});
