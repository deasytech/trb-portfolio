<?php

namespace App\Filament\Concerns;

use App\Enums\PermissionName;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Staff resources with full create, edit and delete pages. Viewing and mutating are
 * separate permissions, and mutations still go through audited services.
 */
trait ManagedStaffResource
{
    abstract protected static function viewPermission(): PermissionName;

    abstract protected static function managePermission(): PermissionName;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $name = $action instanceof UnitEnum ? $action->name : $action;
        $user = auth()->user();

        if ($user === null || ! $user->isActive()) {
            return Response::deny();
        }

        $permission = in_array($name, ['viewAny', 'view'], true) ? static::viewPermission() : static::managePermission();
        $known = in_array($name, ['viewAny', 'view', 'create', 'update', 'delete'], true);

        return $known && $user->can($permission->value) ? Response::allow() : Response::deny();
    }
}
