<?php

use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use Database\Seeders\DemoDataSeeder;

test('the demo seeder creates a labelled customer with balances, holds and a pending transfer', function () {
    $this->seed(DemoDataSeeder::class);

    $customer = User::where('email', 'demo.customer@example.test')->firstOrFail();
    $balance = app(AccountBalanceCalculator::class)->forAccount($customer->accounts()->firstOrFail());

    expect($customer->name)->toContain('DEMO')
        ->and($customer->hasRole('customer'))->toBeTrue()
        ->and($balance->ledgerMinor)->toBe(425_000)
        ->and($balance->availableMinor())->toBe(350_000)
        ->and($customer->transfers)->toHaveCount(1);
});

test('the demo seeder is idempotent', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(User::count())->toBe(1)->and(LedgerTransaction::count())->toBe(2);
});

test('the demo seeder refuses to run in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoDataSeeder)->run())->toThrow(RuntimeException::class);
});
