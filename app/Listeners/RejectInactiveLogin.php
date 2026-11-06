<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Stops suspended users from holding a session, whichever sign-in method completed
 * (password, two-factor challenge or passkey).
 */
class RejectInactiveLogin
{
    /**
     * Sign the user straight back out if their account is not active.
     *
     * @throws ValidationException
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && ! $event->user->isActive()) {
            Auth::guard($event->guard)->logout();

            throw ValidationException::withMessages([
                'email' => __('This account is not active. Please contact support.'),
            ]);
        }
    }
}
