<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;

/**
 * Staff sign in through the Fortify login so two-factor and passkey checks always apply.
 */
class FortifyLogin extends Login
{
    /**
     * Send the visitor to the shared Fortify login page.
     */
    public function mount(): void
    {
        $this->redirectRoute('login');
    }
}
