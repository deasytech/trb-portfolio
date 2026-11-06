<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Staff-managed customer profiles. Every change needs customers.manage, a reason and is audited.
 */
class CustomerService
{
    /**
     * Create the service.
     */
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create a customer with an unusable random password; use sendSetupLink() to email the reset link.
     *
     * @param  array{name: string, email: string, phone?: string|null, country?: string|null}  $data
     */
    public function create(array $data, User $actor, string $reason): User
    {
        $this->authorize($actor);
        $reason = $this->requireReason($reason);
        $this->assertEmailAvailable($data['email']);

        $customer = DB::transaction(function () use ($data, $actor, $reason): User {
            $customer = new User(['name' => trim($data['name']), 'email' => mb_strtolower(trim($data['email'])), 'password' => Str::random(64)]);
            $customer->forceFill([
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
            ])->save();
            $customer->assignRole(RoleName::Customer->value);
            $customer->preference()->create();

            $this->audit->record('customer.created', $customer, $actor, $reason);

            return $customer;
        });

        return $customer;
    }

    /**
     * Email a password-setup link to a customer, returning whether it was sent.
     * Creation never fails because of mail; callers surface a false result and can resend.
     */
    public function sendSetupLink(User $customer, User $actor): bool
    {
        $this->authorize($actor);
        $this->assertCustomer($customer);

        try {
            return Password::broker()->sendResetLink(['email' => $customer->email]) === Password::RESET_LINK_SENT;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Update a customer's contact details.
     *
     * @param  array{name: string, email: string, phone?: string|null, country?: string|null}  $data
     */
    public function update(User $customer, array $data, User $actor, string $reason): User
    {
        $this->authorize($actor);
        $this->assertCustomer($customer);
        $reason = $this->requireReason($reason);
        $this->assertEmailAvailable($data['email'], $customer);

        return DB::transaction(function () use ($customer, $data, $actor, $reason): User {
            $locked = User::query()->lockForUpdate()->findOrFail($customer->id);
            $email = mb_strtolower(trim($data['email']));
            $emailChanged = mb_strtolower($locked->email) !== $email;

            $locked->forceFill([
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
            ]);
            if ($emailChanged) {
                $locked->forceFill(['email_verified_at' => null]);
            }
            $locked->save();

            $this->audit->record('customer.updated', $locked, $actor, $reason, ['email_changed' => $emailChanged]);

            return $locked;
        });
    }

    /**
     * Delete a customer who has no accounts or transfers; anyone with financial history is suspended instead.
     */
    public function delete(User $customer, User $actor, string $reason): void
    {
        $this->authorize($actor);
        $this->assertCustomer($customer);
        $reason = $this->requireReason($reason);

        if ($customer->accounts()->exists() || $customer->transfers()->exists()) {
            throw ValidationException::withMessages(['customer' => 'This customer has financial history. Suspend them instead.']);
        }

        try {
            DB::transaction(function () use ($customer, $actor, $reason): void {
                $locked = User::query()->lockForUpdate()->findOrFail($customer->id);

                $this->audit->record('customer.deleted', $locked, $actor, $reason, ['email' => $locked->email]);
                $locked->delete();
            });
        } catch (QueryException $e) {
            if (! $this->isForeignKeyViolation($e)) {
                throw $e;
            }

            throw ValidationException::withMessages(['customer' => 'This customer has related records. Suspend them instead.']);
        }
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        return stripos($e->getMessage(), 'foreign key') !== false;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->isActive() || ! $actor->can(PermissionName::ManageCustomers->value)) {
            throw new AuthorizationException('You are not permitted to manage customers.');
        }
    }

    private function assertCustomer(User $customer): void
    {
        if (! $customer->hasRole(RoleName::Customer->value) || $customer->isStaff()) {
            throw new AuthorizationException('Only customer profiles can be managed here.');
        }
    }

    private function assertEmailAvailable(string $email, ?User $ignore = null): void
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);
        if ($ignore !== null) {
            $query->whereKeyNot($ignore->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['email' => 'That email address is already in use.']);
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
