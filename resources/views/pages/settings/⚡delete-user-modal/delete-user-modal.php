<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\FundsHold;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

return new class extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        if ($user->accounts()->exists() || $user->transfers()->exists() || FundsHold::query()->where('customer_id', $user->id)->exists()) {
            $this->addError('password', __('Accounts with financial records cannot be deleted here. Please contact support.'));

            return;
        }

        $user->delete();

        // Logout only rotates (and saves) the remember token when one is set; clear it in memory
        // so the deleted user is not re-inserted. If the delete fails, the session is untouched.
        $user->setRememberToken(null);
        $logout();

        $this->redirect('/', navigate: true);
    }
};
