<?php

namespace App\Policies;

use App\Models\Designation;
use App\Models\User;

class DesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('departments.view');
    }

    public function view(User $user, Designation $designation): bool
    {
        return $user->hasPermissionTo('departments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('departments.create') || $user->hasPermissionTo('departments.manage');
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->hasPermissionTo('departments.update') || $user->hasPermissionTo('departments.manage');
    }

    public function delete(User $user, Designation $designation): bool
    {
        return $user->hasPermissionTo('departments.manage');
    }
}
