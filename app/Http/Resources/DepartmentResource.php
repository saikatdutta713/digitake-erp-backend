<?php

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'full_name' => $this->manager->full_name,
                    'official_email' => $this->manager->official_email,
                ] : null;
            }),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                return $this->parent ? [
                    'id' => $this->parent->id,
                    'name' => $this->parent->name,
                    'code' => $this->parent->code,
                ] : null;
            }),
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
            'designations_count' => $this->whenCounted('designations', $this->designations_count ?? $this->designations?->count() ?? 0),
            'designations' => $this->whenLoaded('designations', function () {
                return $this->designations->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'code' => $d->code,
                    'level' => $d->level,
                    'department_id' => $d->department_id,
                    'is_active' => (bool) $d->is_active,
                    'employees_count' => $d->employees_count ?? $d->employees()->count(),
                ]);
            }),
            'roles_count' => $this->whenCounted('roles', $this->roles_count ?? $this->roles?->count() ?? 0),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'is_system_role' => (bool) $r->is_system_role,
                    'role_type' => $r->role_type,
                    'department_id' => $r->department_id,
                    'permissions_count' => $r->permissions->count(),
                    'permissions' => $r->permissions->pluck('name')->values()->all(),
                    'users_count' => $r->users()->count(),
                ]);
            }),
            'department_role' => $this->whenLoaded('roles', function () {
                $role = $this->roles->first();

                return $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('name')->values()->all(),
                    'users_count' => $role->users()->count(),
                ] : null;
            }),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
