<?php

namespace App\Filament\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Turns service-layer refusals into a danger notification instead of an error page.
 */
trait NotifiesServiceErrors
{
    /**
     * Run a service call, returning false (after notifying) when it was refused.
     */
    protected function attempt(Closure $callback): bool
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()->title((string) collect($e->errors())->flatten()->first())->danger()->send();

            return false;
        } catch (AuthorizationException $e) {
            Notification::make()->title($e->getMessage() ?: 'You are not permitted to perform this action.')->danger()->send();

            return false;
        }

        return true;
    }
}
