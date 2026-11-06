<?php

namespace App\Notifications\Concerns;

use App\Models\User;

trait RespectsNotificationPreferences
{
    /**
     * In-app follows the user's preference. Email is only used when it is preferred
     * and a real mail transport is configured, so we never imply an email was sent.
     *
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $preferences = $notifiable->preference()->value('notification_preferences') ?? [];
        $channels = [];

        if ($preferences['in_app'] ?? true) {
            $channels[] = 'database';
        }

        if (($preferences['email'] ?? true) && ! in_array(config('mail.default'), ['log', 'array'], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
