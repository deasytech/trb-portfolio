<?php

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\FundsHold;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\QueryException;

test('accounts expose only a masked identifier', function () {
    $account = Account::factory()->create(['account_number' => '1234567890']);

    expect($account->account_number_last_four)->toBe('7890')
        ->and($account->maskedNumber())->toContain('7890')->not->toContain('123456')
        ->and($account->toArray())->not->toHaveKey('account_number');
});

test('account numbers are unique', function () {
    Account::factory()->create(['account_number' => '1111111111']);

    expect(fn () => Account::factory()->create(['account_number' => '1111111111']))->toThrow(QueryException::class);
});

test('posted ledger transactions cannot be changed or deleted', function () {
    $entry = LedgerTransaction::factory()->create();

    expect(fn () => $entry->update(['amount_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('ledger references are unique', function () {
    LedgerTransaction::factory()->create(['reference' => 'TXN-DUP']);

    expect(fn () => LedgerTransaction::factory()->create(['reference' => 'TXN-DUP']))->toThrow(QueryException::class);
});

test('ledger transactions carry signed amounts', function () {
    expect(LedgerTransaction::factory()->credit(500)->make()->signedAmountMinor())->toBe(500)
        ->and(LedgerTransaction::factory()->debit(500)->make()->signedAmountMinor())->toBe(-500);
});

test('funds holds cannot be deleted', function () {
    expect(fn () => FundsHold::factory()->create()->delete())->toThrow(LogicException::class);
});

test('audit events are append-only', function () {
    $event = AuditEvent::factory()->create();

    expect(fn () => $event->update(['event' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

test('a customer cannot reuse an idempotency key but different customers can', function () {
    $transfer = Transfer::factory()->create(['idempotency_key' => 'key-1']);

    expect(fn () => Transfer::factory()->create(['customer_id' => $transfer->customer_id, 'idempotency_key' => 'key-1']))
        ->toThrow(QueryException::class);

    Transfer::factory()->create(['idempotency_key' => 'key-1']);
    expect(Transfer::query()->where('idempotency_key', 'key-1')->count())->toBe(2);
});

test('transfer destination identifiers are encrypted at rest and hidden', function () {
    $transfer = Transfer::factory()->create(['destination_identifier' => 'GB00SECRET']);

    expect(DB::table('transfers')->value('destination_identifier'))->not->toContain('SECRET')
        ->and($transfer->fresh()->destination_identifier)->toBe('GB00SECRET')
        ->and($transfer->toArray())->not->toHaveKey('destination_identifier');
});

test('users with accounts cannot be deleted', function () {
    $account = Account::factory()->create();

    expect(fn () => $account->user->delete())->toThrow(QueryException::class);
});

test('user relationships resolve', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    UserPreference::factory()->for($user)->create();
    Transfer::factory()->create(['customer_id' => $user->id, 'source_account_id' => $account->id]);

    expect($user->accounts)->toHaveCount(1)
        ->and($user->transfers)->toHaveCount(1)
        ->and($user->preference->theme->value)->toBe('system');
});

test('each user has at most one preference record', function () {
    $user = User::factory()->create();
    UserPreference::factory()->for($user)->create();

    expect(fn () => UserPreference::factory()->for($user)->create())->toThrow(QueryException::class);
});

test('account last four digits are derived even with model events suppressed and kept on unrelated saves', function () {
    $account = Account::withoutEvents(fn () => Account::factory()->create(['account_number' => '1234567890']));

    expect($account->account_number_last_four)->toBe('7890');

    $partial = Account::query()->select('id', 'status')->findOrFail($account->id);
    $partial->status = AccountStatus::Frozen;
    $partial->save();

    expect($account->refresh()->account_number_last_four)->toBe('7890');
});

test('ledger rows cannot be changed or deleted even by bulk queries', function () {
    $row = LedgerTransaction::factory()->create();

    expect(fn () => LedgerTransaction::query()->whereKey($row->id)->update(['amount_minor' => 1]))->toThrow(QueryException::class)
        ->and(fn () => LedgerTransaction::query()->whereKey($row->id)->delete())->toThrow(QueryException::class);
});
