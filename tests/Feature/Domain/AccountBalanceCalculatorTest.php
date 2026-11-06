<?php

use App\Models\Account;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Services\AccountBalance;
use App\Services\AccountBalanceCalculator;

function balanceOf(Account $account): AccountBalance
{
    return app(AccountBalanceCalculator::class)->forAccount($account);
}

test('an account with no activity has a zero balance', function () {
    $balance = balanceOf(Account::factory()->create());

    expect($balance->ledgerMinor)->toBe(0)
        ->and($balance->heldMinor)->toBe(0)
        ->and($balance->availableMinor())->toBe(0);
});

test('ledger balance is posted credits minus posted debits', function () {
    $account = Account::factory()->create();
    LedgerTransaction::factory()->for($account)->credit(10_000)->create();
    LedgerTransaction::factory()->for($account)->credit(2_500)->create();
    LedgerTransaction::factory()->for($account)->debit(4_000)->create();

    expect(balanceOf($account)->ledgerMinor)->toBe(8_500);
});

test('available balance subtracts the remaining amount of active holds', function () {
    $account = Account::factory()->create();
    LedgerTransaction::factory()->for($account)->credit(10_000)->create();
    FundsHold::factory()->for($account)->create(['amount_minor' => 3_000, 'remaining_amount_minor' => 2_000]);

    $balance = balanceOf($account);

    expect($balance->ledgerMinor)->toBe(10_000)
        ->and($balance->heldMinor)->toBe(2_000)
        ->and($balance->availableMinor())->toBe(8_000);
});

test('released and expired holds do not reduce available balance', function () {
    $account = Account::factory()->create();
    LedgerTransaction::factory()->for($account)->credit(10_000)->create();
    FundsHold::factory()->for($account)->released()->create();
    FundsHold::factory()->for($account)->create(['expires_at' => now()->subMinute()]);

    expect(balanceOf($account)->availableMinor())->toBe(10_000);
});

test('pending transfers are not posted to the ledger', function () {
    $account = Account::factory()->create();
    LedgerTransaction::factory()->for($account)->credit(10_000)->create();
    Transfer::factory()->create(['customer_id' => $account->user_id, 'source_account_id' => $account->id, 'amount_minor' => 9_000]);

    expect(balanceOf($account)->availableMinor())->toBe(10_000);
});

test('balances are isolated per account', function () {
    $mine = Account::factory()->create();
    $other = Account::factory()->create();
    LedgerTransaction::factory()->for($other)->credit(99_000)->create();
    FundsHold::factory()->for($other)->create();

    expect(balanceOf($mine)->availableMinor())->toBe(0);
});
