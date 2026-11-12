<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url ?? $this->employee?->avatar_url,
            'status' => $this->status,
            'mfa_enabled' => $this->mfa_enabled,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'roles' => $this->roles->pluck('name')->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'employee' => $this->whenLoaded('employee', function () {
                if (! $this->employee) {
                    return null;
                }

                return [
                    'id' => $this->employee->id,
                    'employee_code' => $this->employee->employee_code,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'full_name' => $this->employee->full_name,
                    'official_email' => $this->employee->official_email,
                    'phone_number' => $this->employee->phone_number,
                    'avatar_url' => $this->employee->avatar_url,
                    'department' => $this->employee->department ? [
                        'id' => $this->employee->department->id,
                        'name' => $this->employee->department->name,
                        'code' => $this->employee->department->code,
                    ] : null,
                    'designation' => $this->employee->designation ? [
                        'id' => $this->employee->designation->id,
                        'name' => $this->employee->designation->name,
                        'code' => $this->employee->designation->code,
                    ] : null,
                    'employment_status' => $this->employee->employment_status,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
