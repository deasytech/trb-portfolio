<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

return new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Load the recovery codes so the user can view them.
     */
    public function viewRecoveryCodes(): void
    {
        if ($this->passwordConfirmationExpired()) {
            return;
        }

        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        if ($this->passwordConfirmationExpired()) {
            return;
        }

        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Redirect to the password confirmation screen when the confirmation is missing or stale.
     */
    private function passwordConfirmationExpired(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt > (int) config('auth.password_timeout', 900)) {
            $this->redirectRoute('password.confirm');

            return true;
        }

        return false;
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        } else {
            $this->recoveryCodes = [];
        }
    }
};
