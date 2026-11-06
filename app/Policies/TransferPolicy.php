<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransferPolicy
{
    /**
     * Customers may only view their own transfers; others get a 404 so ids cannot be probed.
     */
    public function view(User $user, Transfer $transfer): Response
    {
        return $user->id === $transfer->customer_id ? Response::allow() : Response::denyAsNotFound();
    }
}
