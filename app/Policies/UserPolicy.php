<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->hasPermissionTo('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return $actor->id === $target->id || $actor->hasPermissionTo('users.update');
    }

    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false; // Cannot deactivate self
        }

        if ($target->isSuperAdmin()) {
            if (! $actor->isSuperAdmin()) {
                return false;
            }

            // Check if this is the last active Super Admin
            $superAdminCount = User::role('Super Admin')->where('status', 'active')->count();
            if ($superAdminCount <= 1) {
                return false;
            }
        }

        return $actor->hasPermissionTo('users.deactivate');
    }

    public function assignRoles(User $actor, User $target): bool
    {
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return $actor->hasPermissionTo('roles.assign');
    }
}
