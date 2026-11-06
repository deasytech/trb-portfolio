<?php

namespace App\Filament\Concerns;

use App\Enums\PermissionName;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Staff resources expose records read-only; every mutation is a permission-gated
 * action backed by a service so balances and ledgers are never edited directly.
 */
trait ReadOnlyStaffResource
{
    abstract protected static function viewPermission(): PermissionName;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $name = $action instanceof UnitEnum ? $action->name : $action;
        $user = auth()->user();

        $allowed = in_array($name, ['viewAny', 'view'], true)
            && $user !== null
            && $user->isActive()
            && $user->can(static::viewPermission()->value);

        return $allowed ? Response::allow() : Response::deny();
    }
}
