<?php

namespace App\Services;

use App\Enums\HoldStatus;
use App\Enums\PermissionName;
use App\Models\Account;
use App\Models\FundsHold;
use App\Models\Transfer;
use App\Models\User;
use App\Notifications\FundsHoldChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff-only hold placement and (partial) release. Holds are never deleted.
 */
class HoldService
{
    public function __construct(
        private AccountBalanceCalculator $balances,
        private AuditLogger $audit,
    ) {}

    public function place(Account $account, User $actor, int $amountMinor, string $reason, ?Carbon $expiresAt = null, ?Transfer $relatedTransfer = null): FundsHold
    {
        $this->authorize($actor, PermissionName::PlaceHolds);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'The hold amount must be greater than zero.']);
        }
        if ($expiresAt && $expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry must be in the future.']);
        }

        return DB::transaction(function () use ($account, $actor, $amountMinor, $reason, $expiresAt, $relatedTransfer): FundsHold {
            $locked = Account::query()->lockForUpdate()->findOrFail($account->id);

            if ($this->balances->forAccount($locked)->availableMinor() < $amountMinor) {
                throw ValidationException::withMessages(['amount' => 'The hold exceeds the available balance.']);
            }

            $hold = new FundsHold;
            $hold->forceFill([
                'reference' => 'HLD-'.Str::upper(Str::random(12)),
                'account_id' => $locked->id,
                'customer_id' => $locked->user_id,
                'reason' => $reason,
                'amount_minor' => $amountMinor,
                'remaining_amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'status' => HoldStatus::Active,
                'related_transfer_id' => $relatedTransfer?->id,
                'created_by' => $actor->id,
                'expires_at' => $expiresAt,
            ])->save();

            $this->audit->record('hold.placed', $hold, $actor, $reason, ['amount_minor' => $amountMinor, 'currency' => $hold->currency]);
            $locked->user?->notify(new FundsHoldChanged($hold, 'placed'));

            return $hold;
        });
    }

    /**
     * Correct an active hold's stated reason and expiry. The amount is never edited; release and re-place instead.
     */
    public function update(FundsHold $hold, User $actor, string $holdReason, ?Carbon $expiresAt, string $changeReason): FundsHold
    {
        $this->authorize($actor, PermissionName::PlaceHolds);

        $holdReason = trim($holdReason);
        $changeReason = trim($changeReason);
        if ($holdReason === '' || $changeReason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }
        if ($expiresAt && $expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry must be in the future.']);
        }

        return DB::transaction(function () use ($hold, $actor, $holdReason, $expiresAt, $changeReason): FundsHold {
            $locked = FundsHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($locked->effectiveStatus() !== HoldStatus::Active) {
                throw ValidationException::withMessages(['hold' => 'Only active holds can be edited.']);
            }

            $locked->forceFill(['reason' => $holdReason, 'expires_at' => $expiresAt])->save();

            $this->audit->record('hold.updated', $locked, $actor, $changeReason);

            return $locked;
        });
    }

    /**
     * Release all (default) or part of a hold's remaining amount.
     */
    public function release(FundsHold $hold, User $actor, string $reason, ?int $amountMinor = null): FundsHold
    {
        $this->authorize($actor, PermissionName::ReleaseHolds);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return DB::transaction(function () use ($hold, $actor, $reason, $amountMinor): FundsHold {
            $locked = FundsHold::query()->lockForUpdate()->findOrFail($hold->id);

            if ($locked->effectiveStatus() !== HoldStatus::Active) {
                throw ValidationException::withMessages(['hold' => 'Only active holds can be released.']);
            }

            $amount = $amountMinor ?? $locked->remaining_amount_minor;
            if ($amount <= 0 || $amount > $locked->remaining_amount_minor) {
                throw ValidationException::withMessages(['amount' => 'The release amount must be between zero and the remaining hold.']);
            }

            $locked->remaining_amount_minor -= $amount;
            $fullyReleased = $locked->remaining_amount_minor === 0;

            if ($fullyReleased) {
                $locked->forceFill([
                    'status' => HoldStatus::Released,
                    'released_by' => $actor->id,
                    'released_at' => now(),
                    'release_reason' => $reason,
                ]);
            }
            $locked->save();

            $this->audit->record($fullyReleased ? 'hold.released' : 'hold.partially_released', $locked, $actor, $reason, [
                'released_minor' => $amount,
                'remaining_minor' => $locked->remaining_amount_minor,
                'currency' => $locked->currency,
            ]);
            $locked->customer?->notify(new FundsHoldChanged($locked, $fullyReleased ? 'released' : 'partially released'));

            return $locked;
        });
    }

    private function authorize(User $actor, PermissionName $permission): void
    {
        if (! $actor->isActive() || ! $actor->can($permission->value)) {
            throw new AuthorizationException('You are not permitted to perform this action.');
        }
    }
}
