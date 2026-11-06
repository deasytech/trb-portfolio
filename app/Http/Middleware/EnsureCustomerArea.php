<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the customer portal for active, non-staff users only.
 */
class EnsureCustomerArea
{
    /**
     * Redirect staff and sign out inactive users before the request continues.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isActive()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('This account is not active. Please contact support.')]);
        }

        if ($user->isStaff()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
