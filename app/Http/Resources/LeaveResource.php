<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
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
            'user_id' => $this->user_id,
            'applicant_name' => $this->user?->name ?? 'Unknown',
            'official_email' => $this->user?->email,
            'avatar_url' => $this->user?->avatar_url ?? $this->employee?->avatar_url,
            'employee_code' => $this->employee?->employee_code,
            'department' => $this->employee?->department?->name ?? 'Executive Administration',
            'applied_by' => [
                'id' => $this->appliedBy?->id,
                'name' => $this->appliedBy?->name,
                'is_proxy' => $this->applied_by_id !== $this->user_id,
            ],
            'approved_by' => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ] : null,
            'acting_delegate' => $this->actingDelegate ? [
                'id' => $this->actingDelegate->id,
                'name' => $this->actingDelegate->name,
                'email' => $this->actingDelegate->email,
            ] : null,
            'leave_type' => $this->leave_type,
            'start_date' => $this->start_date?->format('Y-m-d') ?? $this->start_date,
            'end_date' => $this->end_date?->format('Y-m-d') ?? $this->end_date,
            'days' => (float) $this->days,
            'half_day_type' => $this->half_day_type,
            'reason' => $this->reason,
            'status' => $this->status,
            'is_auto_approved' => (bool) $this->is_auto_approved,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
