<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /**
     * List audit logs with search and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::with(['actorUser', 'actorEmployee', 'device'])
            ->when($request->filled('action'), function ($q) use ($request) {
                $q->where('action', $request->input('action'));
            })
            ->when($request->filled('actor_user_id'), function ($q) use ($request) {
                $q->where('actor_user_id', $request->input('actor_user_id'));
            })
            ->when($request->filled('correlation_id'), function ($q) use ($request) {
                $q->where('correlation_id', $request->input('correlation_id'));
            })
            ->orderByDesc('created_at');

        $logs = $query->paginate($request->integer('per_page', 25));

        return ApiResponse::success(
            AuditLogResource::collection($logs),
            'Audit logs retrieved.',
            200,
            [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ]
        );
    }

    /**
     * Show single audit log entry.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        Gate::authorize('view', $auditLog);
        $auditLog->load(['actorUser', 'actorEmployee', 'device']);

        return ApiResponse::success(new AuditLogResource($auditLog), 'Audit log entry retrieved.');
    }
}
