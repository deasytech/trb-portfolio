<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Enums\PermissionName;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual ledger postings. Posted rows are immutable, so "create" appends an adjustment
 * and "delete" appends a compensating reversal; nothing is ever edited or removed.
 */
class LedgerService
{
    /**
     * Create the service.
     */
    public function __construct(private AccountBalanceCalculator $balances, private AuditLogger $audit) {}

    /**
     * Post a manual adjustment to an account.
     */
    public function postAdjustment(Account $account, LedgerDirection $direction, int $amountMinor, ?string $description, User $actor, string $reason): LedgerTransaction
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'The amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($account, $direction, $amountMinor, $description, $actor, $reason): LedgerTransaction {
            $locked = Account::query()->lockForUpdate()->findOrFail($account->id);

            if ($locked->status === AccountStatus::Closed) {
                throw ValidationException::withMessages(['account_id' => 'Closed accounts cannot be adjusted.']);
            }

            $this->assertCanDebit($locked, $direction, $amountMinor);

            $entry = LedgerTransaction::create([
                'account_id' => $locked->id,
                'reference' => 'TXN-'.Str::upper(Str::random(12)),
                'type' => LedgerTransactionType::Adjustment,
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'description' => $description,
                'posted_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->audit->record('ledger.adjusted', $entry, $actor, $reason, [
                'direction' => $direction->value,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
            ]);

            return $entry;
        });
    }

    /**
     * Cancel a manual entry with an equal and opposite reversal row.
     * Entries that belong to a transfer are reversed through the transfer instead.
     */
    public function reverseEntry(LedgerTransaction $entry, User $actor, string $reason): LedgerTransaction
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($entry, $actor, $reason): LedgerTransaction {
            $original = LedgerTransaction::query()->findOrFail($entry->id);
            $locked = Account::query()->lockForUpdate()->findOrFail($original->account_id);

            if ($original->type === LedgerTransactionType::Reversal) {
                throw ValidationException::withMessages(['entry' => 'A reversal cannot be reversed.']);
            }
            if ($original->related_type !== null) {
                throw ValidationException::withMessages(['entry' => 'This entry belongs to a transfer. Reverse the transfer instead.']);
            }
            if (LedgerTransaction::query()->where('reverses_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['entry' => 'This entry has already been reversed.']);
            }

            $opposite = $original->direction === LedgerDirection::Credit ? LedgerDirection::Debit : LedgerDirection::Credit;
            $this->assertCanDebit($locked, $opposite, $original->amount_minor);

            $reversal = LedgerTransaction::create([
                'account_id' => $original->account_id,
                'reference' => 'TXN-'.Str::upper(Str::random(12)),
                'type' => LedgerTransactionType::Reversal,
                'direction' => $opposite,
                'amount_minor' => $original->amount_minor,
                'currency' => $original->currency,
                'description' => "Reversal of {$original->reference}",
                'reverses_id' => $original->id,
                'posted_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->audit->record('ledger.reversed', $reversal, $actor, $reason, ['reverses' => $original->reference]);

            return $reversal;
        });
    }

    /**
     * A debit must not take the available balance below zero.
     */
    private function assertCanDebit(Account $account, LedgerDirection $direction, int $amountMinor): void
    {
        if ($direction === LedgerDirection::Debit && $this->balances->forAccount($account)->availableMinor() < $amountMinor) {
            throw ValidationException::withMessages(['amount' => 'The account does not have enough available funds for this debit.']);
        }
    }

    private function authorize(User $actor): void
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::AdjustBalances->value)) {
            throw new AuthorizationException('You are not permitted to adjust balances.');
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return $reason;
    }
}
