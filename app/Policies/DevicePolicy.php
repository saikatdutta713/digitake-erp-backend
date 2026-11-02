<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('devices.view');
    }

    public function view(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.view');
    }

    public function register(User $user): bool
    {
        return $user->hasPermissionTo('devices.manage') || $user->isActive();
    }

    public function revoke(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.revoke');
    }

    public function activate(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.manage');
    }
}
