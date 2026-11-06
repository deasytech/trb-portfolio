<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    /**
     * Store the time of the user's most recent successful login.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $event->user->isActive()) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
