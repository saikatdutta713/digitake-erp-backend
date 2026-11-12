<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all system, departmental, and custom roles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with(['permissions', 'department']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $roles = $query->get()->map(fn (Role $role) => $this->formatRole($role));

        return ApiResponse::success($roles, 'Roles retrieved.');
    }

    /**
     * Create a new role (custom or departmental).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isSuperAdmin() && ! $actor->can('roles.create')) {
            abort(403, 'Unauthorized to create roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'role_type' => ['nullable', 'string', 'in:department,custom'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $roleType = $validated['role_type'] ?? (! empty($validated['department_id']) ? 'department' : 'custom');

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'description' => $validated['description'] ?? null,
            'is_system_role' => false,
            'role_type' => $roleType,
            'department_id' => $roleType === 'department' ? ($validated['department_id'] ?? null) : null,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load(['permissions', 'department']);

        $this->auditService->log(
            'role.created',
            $role,
            null,
            [
                'name' => $role->name,
                'description' => $role->description,
                'role_type' => $role->role_type,
                'department_id' => $role->department_id,
                'permissions' => $role->permissions->pluck('name')->all(),
            ],
            $actor
        );

        return ApiResponse::success($this->formatRole($role), "Role '{$role->name}' created successfully.", 201);
    }

    /**
     * Show single role details.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'department']);

        return ApiResponse::success($this->formatRole($role), 'Role details retrieved.');
    }

    /**
     * Update role details and optional permissions.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isSuperAdmin() && ! $actor->can('roles.update')) {
            abort(403, 'Unauthorized to update roles.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'role_type' => ['nullable', 'string', 'in:department,custom'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name === 'Super Admin' && isset($validated['name']) && $validated['name'] !== $role->name) {
            return ApiResponse::error('SYSTEM_ROLE_PROTECTED', "System predefined role '{$role->name}' cannot be renamed.", 422);
        }

        $oldValues = [
            'name' => $role->name,
            'description' => $role->description,
            'role_type' => $role->role_type,
            'department_id' => $role->department_id,
        ];

        $updateData = [
            'name' => $validated['name'] ?? $role->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $role->description,
        ];

        if ($role->name !== 'Super Admin') {
            if (array_key_exists('role_type', $validated)) {
                $updateData['role_type'] = $validated['role_type'];
            }
            if (array_key_exists('department_id', $validated)) {
                $updateData['department_id'] = $validated['department_id'];
            }
        }

        $role->update($updateData);

        if (isset($validated['permissions'])) {
            if ($role->name !== 'Super Admin') {
                $role->syncPermissions($validated['permissions']);
            }
        }

        $role->load(['permissions', 'department']);

        $this->auditService->log(
            'role.updated',
            $role,
            $oldValues,
            [
                'name' => $role->name,
                'description' => $role->description,
                'role_type' => $role->role_type,
                'department_id' => $role->department_id,
                'permissions' => $role->permissions->pluck('name')->all(),
            ],
            $actor
        );

        return ApiResponse::success($this->formatRole($role), "Role '{$role->name}' updated successfully.");
    }

    /**
     * Delete a role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isSuperAdmin() && ! $actor->can('roles.delete')) {
            abort(403, 'Unauthorized to delete roles.');
        }

        if ($role->name === 'Super Admin' || $role->is_system_role) {
            return ApiResponse::error('SYSTEM_ROLE_PROTECTED', "System role '{$role->name}' is protected and cannot be deleted.", 422);
        }

        $assignedCount = $role->users()->count();
        if ($assignedCount > 0) {
            return ApiResponse::error(
                'ROLE_IN_USE',
                "Cannot delete role '{$role->name}' because {$assignedCount} user(s) currently have this role assigned. Please reassign those users first.",
                422
            );
        }

        $roleName = $role->name;
        $roleId = $role->id;
        $role->delete();

        $this->auditService->log(
            'role.deleted',
            $role,
            ['id' => $roleId, 'name' => $roleName],
            null,
            $actor
        );

        return ApiResponse::success(null, "Role '{$roleName}' deleted successfully.");
    }

    /**
     * List all system permissions grouped by module.
     */
    public function permissions(Request $request): JsonResponse
    {
        $all = Permission::all()->map(fn (Permission $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'module_group' => $p->module_group ?? 'General',
        ]);

        $grouped = $all->groupBy('module_group');

        return ApiResponse::success([
            'all' => $all,
            'grouped' => $grouped,
        ], 'Permissions retrieved.');
    }

    /**
     * Update permissions for a role.
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isSuperAdmin() && ! $actor->can('roles.update') && ! $actor->can('permissions.manage')) {
            abort(403, 'Unauthorized to update role permissions.');
        }

        if ($role->name === 'Super Admin') {
            return ApiResponse::error('IMMUTABLE_ROLE', 'Super Admin permissions are fixed and encompass all system capabilities.', 422);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->all();

        $role->syncPermissions($validated['permissions']);
        $role->load(['permissions', 'department']);

        $this->auditService->log(
            'role.permissions_updated',
            $role,
            ['permissions' => $oldPermissions],
            ['permissions' => $role->permissions->pluck('name')->all()],
            $actor
        );

        return ApiResponse::success($this->formatRole($role), "Permissions updated for role '{$role->name}'.");
    }

    /**
     * Helper to serialize role data with department info.
     *
     * @return array<string, mixed>
     */
    protected function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_system_role' => (bool) $role->is_system_role,
            'role_type' => $role->role_type ?? ($role->name === 'Super Admin' ? 'system' : ($role->department_id ? 'department' : 'custom')),
            'department_id' => $role->department_id,
            'department' => $role->department ? [
                'id' => $role->department->id,
                'name' => $role->department->name,
                'code' => $role->department->code,
            ] : null,
            'users_count' => $role->users()->count(),
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];
    }
}
