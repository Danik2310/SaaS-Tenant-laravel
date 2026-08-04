<?php

namespace App\Shared\Traits;

use Spatie\Permission\Traits\HasRoles;

/**
 * Enforces the `is_active` flag on permissions during authorization.
 *
 * Spatie's built-in `hasPermissionTo()` only matches permission names against
 * assigned roles/permissions; a deactivated permission (is_active = false) would
 * still pass every check. This trait aliases the original implementation and
 * short-circuits it whenever the resolved permission is deactivated, so route
 * middleware, Gate::allows(), and `$user->can()` all reject inactive permissions.
 */
trait EnforcesActivePermissions
{
    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $resolved = $this->filterPermission($permission, $guardName);

        if (! $resolved->is_active) {
            return false;
        }

        return $this->spatieHasPermissionTo($resolved, $guardName);
    }
}
