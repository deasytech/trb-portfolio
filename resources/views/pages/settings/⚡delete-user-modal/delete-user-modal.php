<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use App\Models\FundsHold;
use Livewire\Component;

return new class extends Component {
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

        // Log out first: logging out saves the remember token, which would re-insert a deleted user.
        $logout();
        $user->delete();

        $this->redirect('/', navigate: true);
    }
};
