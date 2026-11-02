<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List all system roles.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system_role' => (bool) $role->is_system_role,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);

        return ApiResponse::success($roles, 'Roles retrieved.');
    }

    /**
     * Show single role details.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return ApiResponse::success([
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_system_role' => (bool) $role->is_system_role,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ], 'Role details retrieved.');
    }
}
