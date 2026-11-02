<?php

namespace App\Modules\DeviceManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\User;
use App\Modules\DeviceManagement\Requests\DeviceRegistrationRequest;
use App\Modules\DeviceManagement\Services\DeviceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceController extends Controller
{
    public function __construct(
        protected DeviceService $deviceService
    ) {}

    /**
     * Register or heartbeat a workstation device.
     */
    public function register(DeviceRegistrationRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $device = $this->deviceService->register($request->validated(), $user);

        return ApiResponse::success(new DeviceResource($device), 'Workstation registered successfully.');
    }

    /**
     * List all registered devices.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Device::class);

        $devices = Device::with('registeredByUser')
            ->orderByDesc('last_seen_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            DeviceResource::collection($devices),
            'Devices retrieved.',
            200,
            [
                'current_page' => $devices->currentPage(),
                'last_page' => $devices->lastPage(),
                'total' => $devices->total(),
            ]
        );
    }

    /**
     * Show device details.
     */
    public function show(Device $device): JsonResponse
    {
        Gate::authorize('view', $device);
        $device->load('registeredByUser');

        return ApiResponse::success(new DeviceResource($device), 'Device details retrieved.');
    }

    /**
     * Revoke a workstation.
     */
    public function revoke(Device $device, Request $request): JsonResponse
    {
        Gate::authorize('revoke', $device);

        /** @var User $actor */
        $actor = $request->user();
        $this->deviceService->revoke($device, $actor);

        return ApiResponse::success(new DeviceResource($device), 'Device revoked and all active sessions terminated.');
    }

    /**
     * Re-activate a revoked workstation.
     */
    public function activate(Device $device, Request $request): JsonResponse
    {
        Gate::authorize('activate', $device);

        /** @var User $actor */
        $actor = $request->user();
        $this->deviceService->activate($device, $actor);

        return ApiResponse::success(new DeviceResource($device), 'Device re-activated successfully.');
    }
}
