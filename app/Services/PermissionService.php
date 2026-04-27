<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    /**
     * Returns true if the user holds the given permission via any of their roles.
     * Super admin role bypasses checks.
     */
    public function userHas(User $user, string $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasPermission($permission);
    }

    public function userHasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userHas($user, $permission)) {
                return true;
            }
        }

        return false;
    }
}
