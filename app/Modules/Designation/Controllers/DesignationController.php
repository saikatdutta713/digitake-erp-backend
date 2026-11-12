<?php

namespace App\Modules\Designation\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignationResource;
use App\Models\Designation;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Designation\Requests\DesignationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DesignationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all designations.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Designation::class);

        $designations = Designation::with('department')
            ->withCount('employees')
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            })
            ->orderBy('level', 'desc')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(DesignationResource::collection($designations), 'Designations retrieved.');
    }

    /**
     * Store new designation.
     */
    public function store(DesignationRequest $request): JsonResponse
    {
        Gate::authorize('create', Designation::class);

        /** @var User $actor */
        $actor = $request->user();
        $designation = Designation::create($request->validated());

        $this->auditService->log('designation.created', $designation, null, $designation->toArray(), $actor);

        $designation->load('department');

        return ApiResponse::success(new DesignationResource($designation), 'Designation created successfully.', 201);
    }

    /**
     * View designation details.
     */
    public function show(Designation $designation): JsonResponse
    {
        Gate::authorize('view', $designation);
        $designation->load('department');

        return ApiResponse::success(new DesignationResource($designation), 'Designation details retrieved.');
    }

    /**
     * Update designation.
     */
    public function update(DesignationRequest $request, Designation $designation): JsonResponse
    {
        Gate::authorize('update', $designation);

        /** @var User $actor */
        $actor = $request->user();
        $old = $designation->only(['name', 'code', 'department_id', 'level', 'is_active']);

        $designation->update($request->validated());

        $this->auditService->log(
            'designation.updated',
            $designation,
            $old,
            $designation->only(['name', 'code', 'department_id', 'level', 'is_active']),
            $actor
        );

        $designation->load('department');

        return ApiResponse::success(new DesignationResource($designation), 'Designation updated successfully.');
    }

    /**
     * Delete designation if no employees have it.
     */
    public function destroy(Request $request, Designation $designation): JsonResponse
    {
        Gate::authorize('delete', $designation);

        if ($designation->employees()->count() > 0) {
            return ApiResponse::error(
                'DESIGNATION_HAS_EMPLOYEES',
                'Cannot delete designation because employees are currently assigned to it.',
                422
            );
        }

        /** @var User $actor */
        $actor = $request->user();
        $old = $designation->toArray();

        $designation->delete();

        $this->auditService->log('designation.deleted', null, $old, null, $actor);

        return ApiResponse::success(null, 'Designation deleted successfully.');
    }
}
