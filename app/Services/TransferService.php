<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerTransactionType;
use App\Enums\PermissionName;
use App\Enums\TransferStatus;
use App\Enums\TransferType;
use App\Exceptions\InvalidTransferTransition;
use App\Models\Account;
use App\Models\LedgerTransaction;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\TransferStatusChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns the transfer lifecycle and every ledger posting a transfer causes.
 *
 * Requests never touch the ledger. Money only moves in complete(), inside one
 * transaction that locks the transfer and its accounts first.
 */
class TransferService
{
    public function __construct(
        private AccountBalanceCalculator $balances,
        private AuditLogger $audit,
        private SettingsService $settings,
    ) {}

    /**
     * Create a pending transfer request. Repeating the same idempotency key returns the original.
     *
     * @param  array{source_account_id?: int|null, destination_account_id?: int|null, beneficiary_name?: string|null, destination_identifier?: string|null, amount_minor: int, description?: string|null}  $data
     *                                                                                                                                                                                                            When a staff actor is given, the request is raised on the customer's behalf and attributed to the actor.
     */
    public function request(User $customer, TransferType $type, array $data, string $idempotencyKey, ?User $actor = null): Transfer
    {
        if ($actor !== null) {
            $this->authorize($actor, PermissionName::ManageTransfers);
        }

        $creator = $actor ?? $customer;

        if ($existing = $this->findByKey($customer, $idempotencyKey)) {
            return $existing;
        }

        if (! $customer->isActive()) {
            throw new AuthorizationException('This profile cannot request transfers.');
        }

        if (! $this->settings->transfersEnabled()) {
            throw ValidationException::withMessages(['amount' => 'Transfer requests are temporarily unavailable. Please try again later.']);
        }

        $amount = $data['amount_minor'];

        // The "primary" account is the one debited (outgoing/internal) or credited (incoming).
        $primaryId = $type === TransferType::IncomingRequest ? ($data['destination_account_id'] ?? null) : ($data['source_account_id'] ?? null);
        $primary = $this->ownedActiveAccount($customer, $primaryId, 'account');
        $destination = null;

        ['min' => $min, 'max' => $max] = $this->settings->transferLimits();

        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an amount between '.Money::format($min, $primary->currency).' and '.Money::format($max, $primary->currency).'.',
            ]);
        }

        if ($type === TransferType::Internal) {
            $destination = $this->ownedActiveAccount($customer, $data['destination_account_id'] ?? null, 'destination');

            if ($destination->is($primary) || $destination->currency !== $primary->currency) {
                throw ValidationException::withMessages(['destination' => 'Choose a different account in the same currency.']);
            }
        }

        if ($type !== TransferType::IncomingRequest && $this->balances->forAccount($primary)->availableMinor() < $amount) {
            throw ValidationException::withMessages(['amount' => 'Your available balance is not sufficient for this transfer.']);
        }

        try {
            $transfer = DB::transaction(function () use ($customer, $creator, $type, $data, $idempotencyKey, $primary, $destination, $amount): Transfer {
                $transfer = new Transfer;
                $transfer->forceFill([
                    'reference' => 'TRF-'.Str::upper(Str::random(12)),
                    'idempotency_key' => $idempotencyKey,
                    'customer_id' => $customer->id,
                    'source_account_id' => $type === TransferType::IncomingRequest ? null : $primary->id,
                    'destination_account_id' => $type === TransferType::IncomingRequest ? $primary->id : $destination?->id,
                    'beneficiary_name' => $data['beneficiary_name'] ?? null,
                    'destination_identifier' => $data['destination_identifier'] ?? null,
                    'type' => $type,
                    'amount_minor' => $amount,
                    'currency' => $primary->currency,
                    'description' => $data['description'] ?? null,
                    'status' => TransferStatus::Pending,
                    'submitted_at' => now(),
                    'created_by' => $creator->id,
                ])->save();

                $this->audit->record('transfer.requested', $transfer, $creator, null, [
                    'type' => $type->value,
                    'amount_minor' => $amount,
                    'currency' => $transfer->currency,
                ]);

                $this->notify($transfer);

                return $transfer;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race.
            return $this->findByKey($customer, $idempotencyKey) ?? throw new \RuntimeException('Duplicate transfer reference.');
        }

        return $transfer;
    }

    public function approve(Transfer $transfer, User $actor): Transfer
    {
        $this->authorize($actor, PermissionName::ApproveTransfers);

        return $this->transition($transfer, TransferStatus::Approved, $actor, 'transfer.approved', function (Transfer $locked): void {
            $locked->approved_at = now();
        });
    }

    public function reject(Transfer $transfer, User $actor, string $reason): Transfer
    {
        $this->authorize($actor, PermissionName::RejectTransfers);
        $reason = $this->requireReason($reason);

        return $this->transition($transfer, TransferStatus::Rejected, $actor, 'transfer.rejected', function (Transfer $locked) use ($reason): void {
            $locked->rejected_at = now();
            $locked->rejection_reason = $reason;
        }, $reason);
    }

    public function cancel(Transfer $transfer, User $customer): Transfer
    {
        if ($transfer->customer_id !== $customer->id) {
            throw new AuthorizationException;
        }

        return $this->transition($transfer, TransferStatus::Cancelled, $customer, 'transfer.cancelled');
    }

    /**
     * Staff correction of a pending transfer's descriptive details; amounts and accounts are never edited.
     *
     * @param  array{description?: string|null, beneficiary_name?: string|null}  $data
     */
    public function updateDetails(Transfer $transfer, User $actor, array $data, string $reason): Transfer
    {
        $this->authorize($actor, PermissionName::ManageTransfers);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($transfer, $actor, $data, $reason): Transfer {
            $locked = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status !== TransferStatus::Pending) {
                throw ValidationException::withMessages(['transfer' => 'Only pending transfers can be edited.']);
            }

            $locked->forceFill([
                'description' => $data['description'] ?? null,
                'beneficiary_name' => $data['beneficiary_name'] ?? null,
            ])->save();

            $this->audit->record('transfer.updated', $locked, $actor, $reason);

            return $locked;
        });
    }

    /**
     * Staff removal of a pending transfer. Records are never deleted; the request is cancelled.
     */
    public function cancelAsStaff(Transfer $transfer, User $actor, string $reason): Transfer
    {
        $this->authorize($actor, PermissionName::ManageTransfers);
        $reason = $this->requireReason($reason);

        return $this->transition($transfer, TransferStatus::Cancelled, $actor, 'transfer.cancelled', null, $reason);
    }

    public function startProcessing(Transfer $transfer, User $actor): Transfer
    {
        $this->authorize($actor, PermissionName::ProcessTransfers);

        return $this->transition($transfer, TransferStatus::Processing, $actor, 'transfer.processing', function (Transfer $locked): void {
            $locked->processed_at = now();
        });
    }

    public function fail(Transfer $transfer, User $actor, string $reason): Transfer
    {
        $this->authorize($actor, PermissionName::ProcessTransfers);
        $reason = $this->requireReason($reason);

        return $this->transition($transfer, TransferStatus::Failed, $actor, 'transfer.failed', function (Transfer $locked) use ($reason): void {
            $locked->rejection_reason = $reason;
        }, $reason);
    }

    /**
     * Post the ledger entries and mark the transfer completed. Completing an already
     * completed transfer is a no-op, so duplicate processing cannot post twice.
     *
     * For external transfers and deposits this records that staff have verified the
     * money movement; the application never simulates it.
     */
    public function complete(Transfer $transfer, User $actor): Transfer
    {
        $this->authorize($actor, PermissionName::ProcessTransfers);

        $completed = DB::transaction(function () use ($transfer, $actor): ?Transfer {
            $locked = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($locked->status === TransferStatus::Completed) {
                return null;
            }

            $this->assertTransition($locked, TransferStatus::Completed);

            $accounts = $this->lockAccounts($locked);
            $this->assertPostable($locked, $accounts);

            foreach ($this->postingsFor($locked) as [$accountId, $postingType, $direction]) {
                LedgerTransaction::create([
                    'account_id' => $accountId,
                    'reference' => 'TXN-'.Str::upper(Str::random(12)),
                    'type' => $postingType,
                    'direction' => $direction,
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'description' => $locked->description ?? "Transfer {$locked->reference}",
                    'related_type' => $locked->getMorphClass(),
                    'related_id' => $locked->id,
                    'posted_at' => now(),
                    'created_by' => $actor->id,
                ]);
            }

            $locked->forceFill(['status' => TransferStatus::Completed, 'completed_at' => now()])->save();

            $this->audit->record('transfer.completed', $locked, $actor, null, [
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
            ]);
            $this->notify($locked);

            return $locked;
        });

        return $completed ?? $transfer->refresh();
    }

    /**
     * Undo a completed transfer with compensating ledger rows; originals are never edited.
     */
    public function reverse(Transfer $transfer, User $actor, string $reason): Transfer
    {
        $this->authorize($actor, PermissionName::ReverseTransfers);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($transfer, $actor, $reason): Transfer {
            $locked = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->assertTransition($locked, TransferStatus::Reversed);
            $accounts = $this->lockAccounts($locked);

            $originals = LedgerTransaction::query()
                ->where('related_type', $locked->getMorphClass())->where('related_id', $locked->id)
                ->where('type', '!=', LedgerTransactionType::Reversal)
                ->get();

            $this->assertReversible($originals, $accounts);

            foreach ($originals as $original) {
                LedgerTransaction::create([
                    'account_id' => $original->account_id,
                    'reference' => 'TXN-'.Str::upper(Str::random(12)),
                    'type' => LedgerTransactionType::Reversal,
                    'direction' => $original->direction === LedgerDirection::Credit ? LedgerDirection::Debit : LedgerDirection::Credit,
                    'amount_minor' => $original->amount_minor,
                    'currency' => $original->currency,
                    'description' => "Reversal of {$original->reference}",
                    'related_type' => $locked->getMorphClass(),
                    'related_id' => $locked->id,
                    'reverses_id' => $original->id,
                    'posted_at' => now(),
                    'created_by' => $actor->id,
                ]);
            }

            $locked->forceFill(['status' => TransferStatus::Reversed])->save();
            $this->audit->record('transfer.reversed', $locked, $actor, $reason, ['reversed_entries' => $originals->count()]);
            $this->notify($locked);

            return $locked;
        });
    }

    /**
     * A reversal that debits an account (such as undoing a deposit) must not overdraw it.
     *
     * @param  \Illuminate\Support\Collection<int, LedgerTransaction>  $originals
     * @param  Collection<int, Account>  $accounts
     */
    private function assertReversible(\Illuminate\Support\Collection $originals, Collection $accounts): void
    {
        $debits = $originals->where('direction', LedgerDirection::Credit)->groupBy('account_id')
            ->map(fn ($rows) => (int) $rows->sum('amount_minor'));

        foreach ($debits as $accountId => $amount) {
            $account = $accounts->firstWhere('id', $accountId) ?? Account::query()->lockForUpdate()->findOrFail($accountId);

            if ($this->balances->forAccount($account)->availableMinor() < $amount) {
                throw ValidationException::withMessages(['transfer' => 'The account no longer has enough available funds to reverse this transfer.']);
            }
        }
    }

    /**
     * @param  (\Closure(Transfer): void)|null  $apply
     */
    private function transition(Transfer $transfer, TransferStatus $to, User $actor, string $event, ?\Closure $apply = null, ?string $reason = null): Transfer
    {
        return DB::transaction(function () use ($transfer, $to, $actor, $event, $apply, $reason): Transfer {
            $locked = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            $this->assertTransition($locked, $to);

            $from = $locked->status;
            if ($apply !== null) {
                $apply($locked);
            }
            $locked->status = $to;
            $locked->save();

            $this->audit->record($event, $locked, $actor, $reason, ['from' => $from->value, 'to' => $to->value]);
            $this->notify($locked);

            return $locked;
        });
    }

    private function assertTransition(Transfer $transfer, TransferStatus $to): void
    {
        if (! $transfer->status->canTransitionTo($to)) {
            throw InvalidTransferTransition::between($transfer->status, $to);
        }
    }

    /**
     * Lock every account involved, lowest id first, to avoid deadlocks.
     *
     * @return Collection<int, Account>
     */
    private function lockAccounts(Transfer $transfer): Collection
    {
        $ids = array_filter([$transfer->source_account_id, $transfer->destination_account_id]);

        return Account::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function assertPostable(Transfer $transfer, Collection $accounts): void
    {
        foreach ($accounts as $account) {
            if ($account->status !== AccountStatus::Active) {
                throw ValidationException::withMessages(['transfer' => "Account {$account->maskedNumber()} is {$account->status->label()}."]);
            }
            if ($account->currency !== $transfer->currency) {
                throw ValidationException::withMessages(['transfer' => 'Transfer currency does not match the account.']);
            }
        }

        if ($transfer->type !== TransferType::IncomingRequest) {
            $source = $accounts->firstWhere('id', $transfer->source_account_id);

            if (! $source || $this->balances->forAccount($source)->availableMinor() < $transfer->amount_minor) {
                throw ValidationException::withMessages(['transfer' => 'Insufficient available balance to complete this transfer.']);
            }
        }
    }

    /**
     * @return list<array{int, LedgerTransactionType, LedgerDirection}>
     */
    private function postingsFor(Transfer $transfer): array
    {
        return match ($transfer->type) {
            TransferType::Internal => [
                [$transfer->source_account_id, LedgerTransactionType::TransferOut, LedgerDirection::Debit],
                [$transfer->destination_account_id, LedgerTransactionType::TransferIn, LedgerDirection::Credit],
            ],
            TransferType::OutgoingRequest => [[$transfer->source_account_id, LedgerTransactionType::Withdrawal, LedgerDirection::Debit]],
            TransferType::IncomingRequest => [[$transfer->destination_account_id, LedgerTransactionType::Deposit, LedgerDirection::Credit]],
        };
    }

    private function ownedActiveAccount(User $customer, int|string|null $id, string $field): Account
    {
        $account = $id ? Account::query()->where('user_id', $customer->id)->find($id) : null;

        if (! $account || $account->status !== AccountStatus::Active) {
            throw ValidationException::withMessages([$field => 'Select one of your active accounts.']);
        }

        return $account;
    }

    private function findByKey(User $customer, string $key): ?Transfer
    {
        return Transfer::query()->where('customer_id', $customer->id)->where('idempotency_key', $key)->first();
    }

    private function authorize(User $actor, PermissionName $permission): void
    {
        if (! $actor->isActive() || ! $actor->can($permission->value)) {
            throw new AuthorizationException('You are not permitted to perform this action.');
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

    private function notify(Transfer $transfer): void
    {
        $transfer->customer?->notify(new TransferStatusChanged($transfer));
    }
}
