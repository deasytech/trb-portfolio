<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\PermissionName;
use App\Enums\UserStatus;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Privileged staff status changes. Each requires a permission and a reason, and is audited.
 */
class AdminActions
{
    public function __construct(private AuditLogger $audit) {}

    public function setCustomerStatus(User $customer, UserStatus $status, User $actor, string $reason): User
    {
        $this->authorize($actor, PermissionName::SuspendCustomers);
        if ($customer->isStaff()) {
            $this->authorize($actor, PermissionName::ManageStaff);
        }
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($customer, $status, $actor, $reason): User {
            $locked = User::query()->lockForUpdate()->findOrFail($customer->id);
            $from = $locked->status;
            $locked->forceFill(['status' => $status])->save();

            $this->audit->record('customer.status_changed', $locked, $actor, $reason, [
                'from' => $from->value,
                'to' => $status->value,
            ]);

            return $locked;
        });
    }

    public function setAccountStatus(Account $account, AccountStatus $status, User $actor, string $reason): Account
    {
        $this->authorize($actor, PermissionName::FreezeAccounts);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($account, $status, $actor, $reason): Account {
            $locked = Account::query()->lockForUpdate()->findOrFail($account->id);
            $from = $locked->status;
            $locked->forceFill(['status' => $status])->save();

            $this->audit->record('account.status_changed', $locked, $actor, $reason, [
                'from' => $from->value,
                'to' => $status->value,
            ]);

            return $locked;
        });
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
}
