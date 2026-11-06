<?php

use App\Enums\HoldStatus;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use App\Services\HoldService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(HoldService::class);
    $this->customer = User::factory()->customer()->create();
    $this->account = Account::factory()->create(['user_id' => $this->customer->id]);
    LedgerTransaction::factory()->for($this->account)->credit(100_000)->create();
    $this->admin = User::factory()->administrator()->create();
});

function available(Account $account): int
{
    return app(AccountBalanceCalculator::class)->forAccount($account)->availableMinor();
}

test('a hold requires a reason and a positive amount', function () {
    expect(fn () => $this->service->place($this->account, $this->admin, 1_000, ' '))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->place($this->account, $this->admin, 0, 'Review'))->toThrow(ValidationException::class);
});

test('an active hold reduces available balance and is audited', function () {
    $hold = $this->service->place($this->account, $this->admin, 30_000, 'Transfer review');

    expect($hold->status)->toBe(HoldStatus::Active)
        ->and($hold->customer_id)->toBe($this->customer->id)
        ->and(available($this->account))->toBe(70_000)
        ->and(AuditEvent::where('event', 'hold.placed')->where('subject_id', $hold->id)->exists())->toBeTrue();
});

test('a hold cannot exceed the available balance', function () {
    $this->service->place($this->account, $this->admin, 90_000, 'First');

    $this->service->place($this->account, $this->admin, 10_001, 'Second');
})->throws(ValidationException::class);

test('customers cannot place or release holds', function () {
    $hold = FundsHold::factory()->for($this->account)->create();

    expect(fn () => $this->service->place($this->account, $this->customer, 1_000, 'Self'))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->service->release($hold, $this->customer, 'Free my money'))->toThrow(AuthorizationException::class);
});

test('a partial release lowers the remaining amount and keeps the hold active', function () {
    $hold = $this->service->place($this->account, $this->admin, 30_000, 'Review');

    $this->service->release($hold, $this->admin, 'Part cleared', 10_000);

    expect($hold->refresh())->status->toBe(HoldStatus::Active)->remaining_amount_minor->toBe(20_000)
        ->and(available($this->account))->toBe(80_000)
        ->and(AuditEvent::where('event', 'hold.partially_released')->where('subject_id', $hold->id)->exists())->toBeTrue();

    expect(fn () => $this->service->release($hold, $this->admin, 'Too much', 20_001))->toThrow(ValidationException::class);
});

test('a full release keeps the hold in history and restores the balance', function () {
    $hold = $this->service->place($this->account, $this->admin, 30_000, 'Review');

    $this->service->release($hold, $this->admin, 'Cleared');

    expect($hold->refresh())->status->toBe(HoldStatus::Released)->remaining_amount_minor->toBe(0)
        ->released_by->toBe($this->admin->id)->release_reason->toBe('Cleared')
        ->and(FundsHold::count())->toBe(1)
        ->and(available($this->account))->toBe(100_000)
        ->and(AuditEvent::where('event', 'hold.released')->where('reason', 'Cleared')->exists())->toBeTrue()
        ->and(fn () => $this->service->release($hold, $this->admin, 'Again'))->toThrow(ValidationException::class);
});
