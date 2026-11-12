<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
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
            'code' => $this->code,
            'days_per_year' => (float) $this->days_per_year,
            'is_paid' => (bool) $this->is_paid,
            'requires_approval' => (bool) $this->requires_approval,
            'allow_half_day' => (bool) $this->allow_half_day,
            'carry_forward_days' => (float) $this->carry_forward_days,
            'applicable_gender' => $this->applicable_gender,
            'color' => $this->color ?? '#10b981',
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
