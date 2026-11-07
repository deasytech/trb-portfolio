<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\PermissionName;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff opening, editing and removal of customer accounts. Balances are never set here; they come from the ledger.
 */
class AccountService
{
    /**
     * Create the service.
     */
    public function __construct(private AuditLogger $audit) {}

    /**
     * Open an active, zero-balance account for a customer.
     */
    public function open(User $customer, AccountType $type, string $currency, User $actor, string $reason): Account
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);
        $currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages(['currency' => 'Enter a three-letter currency code.']);
        }

        if (! $customer->hasRole('customer') || ! $customer->isActive()) {
            throw ValidationException::withMessages(['user_id' => 'Choose an active customer.']);
        }

        return DB::transaction(function () use ($customer, $type, $currency, $actor, $reason): Account {
            $account = new Account;
            $account->forceFill([
                'user_id' => $customer->id,
                'account_number' => $this->generateNumber(),
                'account_type' => $type,
                'currency' => $currency,
                'status' => AccountStatus::Active,
                'opened_at' => now(),
            ])->save();

            $this->audit->record('account.opened', $account, $actor, $reason, ['type' => $type->value, 'currency' => $currency]);

            return $account;
        });
    }

    /**
     * Change the account type. Currency and owner are fixed once opened.
     */
    public function update(Account $account, AccountType $type, User $actor, string $reason): Account
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($account, $type, $actor, $reason): Account {
            $locked = Account::query()->lockForUpdate()->findOrFail($account->id);
            $from = $locked->account_type;

            $locked->forceFill(['account_type' => $type])->save();

            $this->audit->record('account.updated', $locked, $actor, $reason, ['from' => $from->value, 'to' => $type->value]);

            return $locked;
        });
    }

    /**
     * Delete an account only if it never held money or activity; otherwise it should be frozen.
     */
    public function delete(Account $account, User $actor, string $reason): void
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($account, $actor, $reason): void {
            $locked = Account::query()->lockForUpdate()->findOrFail($account->id);

            $hasHistory = $locked->ledgerTransactions()->exists()
                || $locked->holds()->exists()
                || Transfer::query()->where('source_account_id', $locked->id)->orWhere('destination_account_id', $locked->id)->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages(['account' => 'This account has financial history and cannot be deleted. Freeze it instead.']);
            }

            $this->audit->record('account.deleted', $locked, $actor, $reason, ['last_four' => $locked->account_number_last_four]);
            $locked->delete();
        });
    }

    private function generateNumber(): string
    {
        do {
            $number = (string) random_int(1_000_000_000, 9_999_999_999);
        } while (Account::query()->where('account_number', $number)->exists());

        return $number;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::ManageAccounts->value)) {
            throw new AuthorizationException('You are not permitted to manage accounts.');
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
