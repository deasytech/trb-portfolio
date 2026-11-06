<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff role and activation changes. Only users holding staff.manage may use it,
 * nobody may change their own access, and the last super administrator is protected.
 */
class StaffService
{
    public function __construct(private AuditLogger $audit) {}

    public function changeRole(User $staff, RoleName $role, User $actor, string $reason): User
    {
        $this->authorizeFor($staff, $actor);

        if (! in_array($role, RoleName::staff(), true)) {
            throw ValidationException::withMessages(['role' => 'Choose a staff role.']);
        }

        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($staff, $role, $actor, $reason): User {
            $locked = User::query()->lockForUpdate()->findOrFail($staff->id);
            $from = $locked->roles()->pluck('name')->sort()->values()->all();

            if ($from === [$role->value]) {
                return $locked;
            }

            if ($locked->hasRole(RoleName::SuperAdministrator->value) && $role !== RoleName::SuperAdministrator) {
                $this->assertAnotherActiveSuperAdministrator($locked);
            }

            $locked->syncRoles([$role->value]);

            $this->audit->record('staff.role_changed', $locked, $actor, $reason, [
                'from' => $from,
                'to' => [$role->value],
            ]);

            return $locked;
        });
    }

    public function setStatus(User $staff, UserStatus $status, User $actor, string $reason): User
    {
        $this->authorizeFor($staff, $actor);
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($staff, $status, $actor, $reason): User {
            $locked = User::query()->lockForUpdate()->findOrFail($staff->id);

            if ($locked->status === $status) {
                return $locked;
            }

            if ($status !== UserStatus::Active && $locked->hasRole(RoleName::SuperAdministrator->value)) {
                $this->assertAnotherActiveSuperAdministrator($locked);
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $status])->save();

            $this->audit->record('staff.status_changed', $locked, $actor, $reason, [
                'from' => $from->value,
                'to' => $status->value,
            ]);

            return $locked;
        });
    }

    private function authorizeFor(User $staff, User $actor): void
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::ManageStaff->value)) {
            throw new AuthorizationException('You are not permitted to manage staff.');
        }

        if (! $staff->hasAnyRole(RoleName::staffValues())) {
            throw new AuthorizationException('Only staff accounts can be managed here.');
        }

        if ($staff->is($actor)) {
            throw new AuthorizationException('You cannot change your own access.');
        }
    }

    private function assertAnotherActiveSuperAdministrator(User $staff): void
    {
        $others = User::query()
            ->role(RoleName::SuperAdministrator->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($staff->id)
            ->lockForUpdate()
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages(['role' => 'At least one active super administrator must remain.']);
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
