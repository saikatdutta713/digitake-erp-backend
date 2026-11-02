<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'official_email' => $this->official_email,
            'phone_number' => $this->phone_number,
            'avatar_url' => $this->avatar_url,
            'date_of_joining' => $this->date_of_joining->toDateString(),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'code' => $this->department->code,
                ] : null;
            }),
            'designation_id' => $this->designation_id,
            'designation' => $this->whenLoaded('designation', function () {
                return $this->designation ? [
                    'id' => $this->designation->id,
                    'name' => $this->designation->name,
                    'code' => $this->designation->code,
                ] : null;
            }),
            'reporting_manager_id' => $this->reporting_manager_id,
            'reporting_manager' => $this->whenLoaded('reportingManager', function () {
                return $this->reportingManager ? [
                    'id' => $this->reportingManager->id,
                    'full_name' => $this->reportingManager->full_name,
                ] : null;
            }),
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
