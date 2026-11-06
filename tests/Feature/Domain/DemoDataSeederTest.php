<?php

use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use Database\Seeders\DatabaseSeeder;
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

test('the demo seeder only runs in local or testing environments', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    expect(fn () => (new DemoDataSeeder)->run())->toThrow(RuntimeException::class)
        ->and(User::count())->toBe(0);
})->with(['production', 'staging']);

test('the database seeder seeds demo data locally but not in production', function () {
    $this->app->detectEnvironment(fn () => 'production');
    (new DatabaseSeeder)->setContainer(app())->run();
    expect(User::count())->toBe(0);

    $this->app->detectEnvironment(fn () => 'local');
    (new DatabaseSeeder)->setContainer(app())->run();
    expect(User::where('email', 'demo.customer@example.test')->exists())->toBeTrue();
});

test('the demo seeder recovers missing records without duplicating existing ones', function () {
    $this->seed(DemoDataSeeder::class);
    FundsHold::query()->where('reason', 'Transfer Review')->getQuery()->delete();
    Transfer::query()->delete();

    $this->seed(DemoDataSeeder::class);

    expect(FundsHold::count())->toBe(2)->and(Transfer::count())->toBe(1)->and(LedgerTransaction::count())->toBe(2);
});

test('the demo seeder never alters an existing non-demo user with the demo email', function () {
    $user = User::factory()->unverified()->create(['email' => 'demo.customer@example.test', 'name' => 'Real Person']);

    expect(fn () => (new DemoDataSeeder)->run())->toThrow(RuntimeException::class);

    $user->refresh();
    expect($user->email_verified_at)->toBeNull()->and($user->roles)->toBeEmpty();
});
