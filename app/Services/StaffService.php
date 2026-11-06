<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff role and activation changes. Only users holding staff.manage may use it,
 * nobody may change their own access, and the last super administrator is protected.
 */
class StaffService
{
    /**
     * Create the service.
     */
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create a staff account with an unusable random password and email a reset link,
     * so no credential is ever chosen or shown by the person creating it.
     */
    public function create(string $name, string $email, RoleName $role, User $actor, string $reason): User
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::ManageStaff->value)) {
            throw new AuthorizationException('You are not permitted to manage staff.');
        }

        if (! in_array($role, RoleName::staff(), true)) {
            throw ValidationException::withMessages(['role' => 'Choose a staff role.']);
        }

        $name = trim($name);
        $email = mb_strtolower(trim($email));
        $reason = $this->requireReason($reason);

        if ($name === '' || mb_strlen($name) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['email' => 'Enter a name and a valid email address.']);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }

        try {
            $staff = DB::transaction(function () use ($name, $email, $role, $actor, $reason): User {
                $staff = new User(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);
                $staff->forceFill(['email_verified_at' => now(), 'status' => UserStatus::Active])->save();
                $staff->assignRole($role->value);

                $this->audit->record('staff.created', $staff, $actor, $reason, ['role' => $role->value]);

                return $staff;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }

        if (Password::broker()->sendResetLink(['email' => $staff->email]) !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => 'The account was created but the password-setup link could not be sent.']);
        }

        return $staff;
    }

    /**
     * Update a staff member's name and email, with a reason and an audit event.
     */
    public function update(User $staff, string $name, string $email, User $actor, string $reason): User
    {
        $this->authorizeFor($staff, $actor, allowSelf: true);
        $reason = $this->requireReason($reason);
        $name = trim($name);
        $email = mb_strtolower(trim($email));

        if ($name === '' || mb_strlen($name) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['email' => 'Enter a name and a valid email address.']);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($staff->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
        }

        return DB::transaction(function () use ($staff, $name, $email, $actor, $reason): User {
            $locked = User::query()->lockForUpdate()->findOrFail($staff->id);
            $emailChanged = mb_strtolower($locked->email) !== $email;

            $locked->forceFill(['name' => $name, 'email' => $email]);
            if ($emailChanged) {
                $locked->forceFill(['email_verified_at' => now()]);
            }
            $locked->save();

            $this->audit->record('staff.updated', $locked, $actor, $reason, ['email_changed' => $emailChanged]);

            return $locked;
        });
    }

    /**
     * Delete a staff account that has no recorded activity; otherwise it must be deactivated instead.
     */
    public function delete(User $staff, User $actor, string $reason): void
    {
        $this->authorizeFor($staff, $actor);
        $reason = $this->requireReason($reason);

        try {
            DB::transaction(function () use ($staff, $actor, $reason): void {
                $locked = User::query()->lockForUpdate()->findOrFail($staff->id);

                if ($locked->hasRole(RoleName::SuperAdministrator->value)) {
                    $this->assertAnotherActiveSuperAdministrator($locked);
                }

                $this->audit->record('staff.deleted', $locked, $actor, $reason, ['email' => $locked->email]);
                $locked->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['staff' => 'This staff member has recorded activity. Deactivate them instead.']);
        }
    }

    /**
     * Replace a staff member's role, with a reason and an audit event.
     */
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

    /**
     * Activate or deactivate a staff member, with a reason and an audit event.
     */
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

    /**
     * Require a permitted actor acting on another staff account.
     */
    private function authorizeFor(User $staff, User $actor, bool $allowSelf = false): void
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::ManageStaff->value)) {
            throw new AuthorizationException('You are not permitted to manage staff.');
        }

        if (! $staff->hasAnyRole(RoleName::staffValues())) {
            throw new AuthorizationException('Only staff accounts can be managed here.');
        }

        if (! $allowSelf && $staff->is($actor)) {
            throw new AuthorizationException('You cannot change your own access.');
        }
    }

    /**
     * Refuse to remove the last active super administrator.
     */
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

    /**
     * Trim the reason and reject a blank one.
     */
    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return $reason;
    }
}
