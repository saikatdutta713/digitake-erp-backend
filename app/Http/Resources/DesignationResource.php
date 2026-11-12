<?php

namespace App\Http\Resources;

use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Designation
 */
class DesignationResource extends JsonResource
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
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'code' => $this->department->code,
                ] : null;
            }),
            'level' => $this->level,
            'is_active' => $this->is_active,
            'employees_count' => $this->employees_count ?? $this->employees()->count(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
