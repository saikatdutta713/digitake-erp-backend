<?php

namespace App\Modules\DeviceManagement\Services;

use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Register or update a workstation machine.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?User $actor = null): Device
    {
        $device = Device::updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'device_name' => $data['device_name'],
                'device_type' => $data['device_type'] ?? 'personal',
                'platform' => 'windows',
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'],
                'registered_by' => $actor?->id,
                'last_seen_at' => now(),
            ]
        );

        $this->auditService->log('device.registered', $device, null, [
            'device_uuid' => $device->device_uuid,
            'device_type' => $device->device_type,
        ], $actor);

        return $device;
    }

    /**
     * Revoke a workstation, instantly ending its active sessions and Sanctum tokens.
     */
    public function revoke(Device $device, User $actor): void
    {
        $oldStatus = $device->status;
        $device->update(['status' => 'revoked']);

        // Revoke all active sanctum tokens associated with this device's sessions
        $activeSessions = DeviceSession::where('device_id', $device->id)
            ->where('session_status', 'active')
            ->get();

        foreach ($activeSessions as $session) {
            if ($session->sanctum_token_id) {
                PersonalAccessToken::where('id', $session->sanctum_token_id)->delete();
            }
            $session->update([
                'session_status' => 'revoked_by_admin',
                'ended_at' => now(),
            ]);
        }

        $this->auditService->log(
            'device.revoked',
            $device,
            ['status' => $oldStatus],
            ['status' => 'revoked'],
            $actor
        );
    }

    /**
     * Re-activate a previously revoked workstation.
     */
    public function activate(Device $device, User $actor): void
    {
        $oldStatus = $device->status;
        $device->update(['status' => 'active']);

        $this->auditService->log(
            'device.activated',
            $device,
            ['status' => $oldStatus],
            ['status' => 'active'],
            $actor
        );
    }
}
