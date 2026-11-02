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
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
