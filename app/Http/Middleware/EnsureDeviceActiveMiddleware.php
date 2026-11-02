<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceActiveMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUuid = $request->header('X-Device-UUID');

        if ($deviceUuid && is_string($deviceUuid)) {
            $device = Device::where('device_uuid', $deviceUuid)->first();

            if ($device) {
                if ($device->status === 'revoked') {
                    return ApiResponse::error(
                        'DEVICE_REVOKED',
                        'This workstation has been revoked by an administrator.',
                        403
                    );
                }

                $device->updateQuietly(['last_seen_at' => now()]);
                $request->attributes->set('current_device', $device);
            }
        }

        return $next($request);
    }
}
