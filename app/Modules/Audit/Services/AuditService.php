<?php

namespace App\Modules\Audit\Services;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Log an auditable business or security event.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null
    ): AuditLog {
        $request = request();

        $user = $actor ?? auth('sanctum')->user() ?? auth()->user();
        $employeeId = $user?->employee?->id;

        $device = null;
        if ($request) {
            $device = $request->attributes->get('current_device');
            if (! $device && $request->header('X-Device-UUID')) {
                $device = Device::where('device_uuid', $request->header('X-Device-UUID'))->first();
            }
        }

        $correlationId = $request ? $request->attributes->get('correlation_id') : null;
        if (! $correlationId) {
            $correlationId = Str::uuid()->toString();
        }

        return AuditLog::create([
            'actor_user_id' => $user?->id,
            'actor_employee_id' => $employeeId,
            'action' => $action,
            'auditable_type' => $entity ? get_class($entity) : null,
            'auditable_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request ? ($request->ip() ?? '127.0.0.1') : '127.0.0.1',
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'device_id' => $device?->id,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }
}
