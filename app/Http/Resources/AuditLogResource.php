<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_user_id' => $this->actor_user_id,
            'actor_user' => $this->whenLoaded('actorUser', function () {
                return $this->actorUser ? [
                    'id' => $this->actorUser->id,
                    'name' => $this->actorUser->name,
                    'email' => $this->actorUser->email,
                ] : null;
            }),
            'actor_employee_id' => $this->actor_employee_id,
            'actor_employee' => $this->whenLoaded('actorEmployee', function () {
                return $this->actorEmployee ? [
                    'id' => $this->actorEmployee->id,
                    'full_name' => $this->actorEmployee->full_name,
                    'employee_code' => $this->actorEmployee->employee_code,
                ] : null;
            }),
            'action' => $this->action,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_id' => $this->device_id,
            'device' => $this->whenLoaded('device', function () {
                return $this->device ? [
                    'id' => $this->device->id,
                    'device_name' => $this->device->device_name,
                    'device_type' => $this->device->device_type,
                ] : null;
            }),
            'correlation_id' => $this->correlation_id,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
