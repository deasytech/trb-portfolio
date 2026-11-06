<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Exceptions\InvalidTransferTransition;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\TransferStatusChanged;
use App\Services\AccountBalanceCalculator;
use App\Services\TransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(TransferService::class);
    $this->customer = User::factory()->customer()->create();
    $this->account = Account::factory()->create(['user_id' => $this->customer->id]);
    LedgerTransaction::factory()->for($this->account)->credit(100_000)->create();
    $this->admin = User::factory()->administrator()->create();
    $this->super = User::factory()->superAdministrator()->create();
});

function requestOut(array $overrides = [], ?string $key = null): Transfer
{
    return test()->service->request(test()->customer, TransferType::OutgoingRequest, array_merge([
        'source_account_id' => test()->account->id,
        'beneficiary_name' => 'Jane Roe',
        'destination_identifier' => '123456789',
        'amount_minor' => 30_000,
    ], $overrides), $key ?? fake()->uuid());
}

function availableOf(Account $account): int
{
    return app(AccountBalanceCalculator::class)->forAccount($account)->availableMinor();
}

test('a valid request is pending, uniquely referenced, audited and not posted to the ledger', function () {
    Notification::fake();

    $a = requestOut();
    $b = requestOut();

    expect($a->status)->toBe(TransferStatus::Pending)
        ->and($a->reference)->not->toBe($b->reference)
        ->and($a->currency)->toBe('USD')
        ->and(LedgerTransaction::where('related_id', $a->id)->count())->toBe(0)
        ->and(availableOf($this->account))->toBe(100_000)
        ->and(AuditEvent::where('event', 'transfer.requested')->where('subject_id', $a->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->customer, TransferStatusChanged::class);
});

test('duplicate submissions with the same idempotency key create one transfer', function () {
    $first = requestOut(key: 'key-1');
    $second = requestOut(key: 'key-1');

    expect($second->is($first))->toBeTrue()
        ->and(Transfer::count())->toBe(1);
});

test('invalid amounts are rejected', function (int $amount) {
    requestOut(['amount_minor' => $amount]);
})->with([0, -500, 99, 10_000_001])->throws(ValidationException::class);

test('an amount above the available balance is rejected', function () {
    requestOut(['amount_minor' => 100_001]);
})->throws(ValidationException::class);

test('a customer cannot transfer from an account they do not own', function () {
    $other = Account::factory()->create();
    LedgerTransaction::factory()->for($other)->credit(50_000)->create();

    requestOut(['source_account_id' => $other->id]);
})->throws(ValidationException::class);

test('a frozen account cannot be used', function () {
    $this->account->update(['status' => 'frozen']);

    requestOut();
})->throws(ValidationException::class);

test('approval and rejection enforce allowed transitions', function () {
    $transfer = requestOut();

    $this->service->approve($transfer, $this->admin);
    expect($transfer->refresh()->status)->toBe(TransferStatus::Approved);

    expect(fn () => $this->service->approve($transfer, $this->admin))->toThrow(InvalidTransferTransition::class);

    $this->service->reject($transfer, $this->admin, 'Beneficiary could not be verified');
    expect($transfer->refresh())
        ->status->toBe(TransferStatus::Rejected)
        ->rejection_reason->toBe('Beneficiary could not be verified');

    expect(fn () => $this->service->complete($transfer, $this->admin))->toThrow(InvalidTransferTransition::class);
});

test('rejection requires a reason and staff permissions', function () {
    $transfer = requestOut();

    expect(fn () => $this->service->reject($transfer, $this->admin, '  '))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->approve($transfer, $this->customer))->toThrow(AuthorizationException::class);
});

test('a customer can cancel only their own pending transfer', function () {
    $transfer = requestOut();

    expect(fn () => $this->service->cancel($transfer, User::factory()->customer()->create()))->toThrow(AuthorizationException::class);

    $this->service->cancel($transfer, $this->customer);
    expect($transfer->refresh()->status)->toBe(TransferStatus::Cancelled);
});

test('completing an outgoing transfer posts a single debit and is idempotent', function () {
    $transfer = requestOut();
    $this->service->approve($transfer, $this->admin);

    $this->service->complete($transfer, $this->admin);
    $this->service->complete($transfer, $this->admin);

    $rows = LedgerTransaction::where('related_id', $transfer->id)->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->direction)->toBe(LedgerDirection::Debit)
        ->and($rows->first()->type)->toBe(LedgerTransactionType::Withdrawal)
        ->and($transfer->refresh()->status)->toBe(TransferStatus::Completed)
        ->and(availableOf($this->account))->toBe(70_000);
});

test('an incoming deposit credits the account only once staff complete it', function () {
    $transfer = $this->service->request($this->customer, TransferType::IncomingRequest, [
        'destination_account_id' => $this->account->id,
        'beneficiary_name' => 'Acme Ltd',
        'amount_minor' => 25_000,
    ], 'dep-1');

    expect($transfer->source_account_id)->toBeNull()
        ->and(availableOf($this->account))->toBe(100_000);

    $this->service->approve($transfer, $this->admin);
    $this->service->complete($transfer, $this->admin);

    expect(availableOf($this->account))->toBe(125_000);
});

test('an internal transfer posts balanced debit and credit entries', function () {
    $savings = Account::factory()->create(['user_id' => $this->customer->id]);
    $transfer = $this->service->request($this->customer, TransferType::Internal, [
        'source_account_id' => $this->account->id,
        'destination_account_id' => $savings->id,
        'amount_minor' => 40_000,
    ], 'int-1');

    $this->service->approve($transfer, $this->admin);
    $this->service->complete($transfer, $this->admin);

    $rows = LedgerTransaction::where('related_id', $transfer->id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->where('direction', LedgerDirection::Debit)->sum('amount_minor'))->toBe(40_000)
        ->and($rows->where('direction', LedgerDirection::Credit)->sum('amount_minor'))->toBe(40_000)
        ->and(availableOf($this->account))->toBe(60_000)
        ->and(availableOf($savings))->toBe(40_000);
});

test('internal transfers require another own account in the same currency', function () {
    $euro = Account::factory()->create(['user_id' => $this->customer->id, 'currency' => 'EUR']);

    expect(fn () => $this->service->request($this->customer, TransferType::Internal, [
        'source_account_id' => $this->account->id, 'destination_account_id' => $this->account->id, 'amount_minor' => 1_000,
    ], 'k1'))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->request($this->customer, TransferType::Internal, [
            'source_account_id' => $this->account->id, 'destination_account_id' => $euro->id, 'amount_minor' => 1_000,
        ], 'k2'))->toThrow(ValidationException::class);
});

test('the same funds cannot be spent twice across competing transfers', function () {
    $first = requestOut(['amount_minor' => 80_000]);
    $second = requestOut(['amount_minor' => 80_000]);
    $this->service->approve($first, $this->admin);
    $this->service->approve($second, $this->admin);

    $this->service->complete($first, $this->admin);

    expect(fn () => $this->service->complete($second, $this->admin))->toThrow(ValidationException::class)
        ->and($second->refresh()->status)->toBe(TransferStatus::Approved)
        ->and(LedgerTransaction::where('related_id', $second->id)->count())->toBe(0)
        ->and(availableOf($this->account))->toBe(20_000);
});

test('an active hold prevents completing a transfer that would use held funds', function () {
    FundsHold::factory()->for($this->account)->create(['amount_minor' => 90_000, 'remaining_amount_minor' => 90_000]);
    $transfer = requestOut(['amount_minor' => 5_000]);
    $this->service->approve($transfer, $this->admin);
    FundsHold::query()->update(['amount_minor' => 99_000, 'remaining_amount_minor' => 99_000]);

    expect(fn () => $this->service->complete($transfer, $this->admin))->toThrow(ValidationException::class);
});

test('a deposit cannot be reversed once its funds have been spent', function () {
    $deposit = $this->service->request($this->customer, TransferType::IncomingRequest, [
        'destination_account_id' => $this->account->id, 'beneficiary_name' => 'Acme', 'amount_minor' => 50_000,
    ], 'dep-rev');
    $this->service->approve($deposit, $this->admin);
    $this->service->complete($deposit, $this->admin);

    $spend = requestOut(['amount_minor' => 140_000]);
    $this->service->approve($spend, $this->admin);
    $this->service->complete($spend, $this->admin);

    expect(fn () => $this->service->reverse($deposit, $this->super, 'Mistake'))->toThrow(ValidationException::class)
        ->and($deposit->refresh()->status)->toBe(TransferStatus::Completed);
});

test('the amount limit message uses the account currency', function () {
    $this->account->update(['currency' => 'EUR']);

    try {
        requestOut(['amount_minor' => 1]);
    } catch (ValidationException $e) {
        expect($e->errors()['amount'][0])->toContain('EUR');
    }
});

test('a completed transfer is reversed with compensating entries and an audit event', function () {
    $transfer = requestOut();
    $this->service->approve($transfer, $this->admin);
    $this->service->complete($transfer, $this->admin);

    expect(fn () => $this->service->reverse($transfer, $this->admin, 'Customer dispute'))->toThrow(AuthorizationException::class);

    $this->service->reverse($transfer, $this->super, 'Customer dispute');

    $original = LedgerTransaction::where('related_id', $transfer->id)->where('type', LedgerTransactionType::Withdrawal)->first();
    $reversal = LedgerTransaction::where('related_id', $transfer->id)->where('type', LedgerTransactionType::Reversal)->first();

    expect($transfer->refresh()->status)->toBe(TransferStatus::Reversed)
        ->and($reversal->reverses_id)->toBe($original->id)
        ->and($reversal->direction)->toBe(LedgerDirection::Credit)
        ->and(availableOf($this->account))->toBe(100_000)
        ->and(AuditEvent::where('event', 'transfer.reversed')->where('reason', 'Customer dispute')->exists())->toBeTrue()
        ->and(fn () => $this->service->reverse($transfer, $this->super, 'again'))->toThrow(InvalidTransferTransition::class);
});

test('a completed transfer cannot be sent back to pending', function () {
    $transfer = requestOut();
    $this->service->approve($transfer, $this->admin);
    $this->service->complete($transfer, $this->admin);

    expect(fn () => $this->service->cancel($transfer, $this->customer))->toThrow(InvalidTransferTransition::class)
        ->and(fn () => $this->service->reject($transfer, $this->admin, 'x'))->toThrow(InvalidTransferTransition::class);
});
