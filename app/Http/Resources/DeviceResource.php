<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_uuid' => $this->device_uuid,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'platform' => $this->platform,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'status' => $this->status,
            'registered_by' => $this->registered_by,
            'registered_by_user' => $this->whenLoaded('registeredByUser', function () {
                return $this->registeredByUser ? [
                    'id' => $this->registeredByUser->id,
                    'name' => $this->registeredByUser->name,
                ] : null;
            }),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
