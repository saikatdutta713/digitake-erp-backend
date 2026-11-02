<?php

namespace App\Modules\Department\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Department\Requests\DepartmentRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all departments.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::with(['manager', 'parent'])
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(DepartmentResource::collection($departments), 'Departments retrieved.');
    }

    /**
     * Store new department.
     */
    public function store(DepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        /** @var User $actor */
        $actor = $request->user();
        $department = Department::create($request->validated());

        $this->auditService->log('department.created', $department, null, $department->toArray(), $actor);

        $department->load(['manager', 'parent']);

        return ApiResponse::success(new DepartmentResource($department), 'Department created successfully.', 201);
    }

    /**
     * View single department.
     */
    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', $department);
        $department->load(['manager', 'parent', 'designations'])->loadCount('employees');

        return ApiResponse::success(new DepartmentResource($department), 'Department details retrieved.');
    }

    /**
     * Update department.
     */
    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        Gate::authorize('update', $department);

        /** @var User $actor */
        $actor = $request->user();
        $old = $department->only(['name', 'code', 'manager_id', 'parent_id', 'is_active']);

        $department->update($request->validated());

        $this->auditService->log(
            'department.updated',
            $department,
            $old,
            $department->only(['name', 'code', 'manager_id', 'parent_id', 'is_active']),
            $actor
        );

        $department->load(['manager', 'parent']);

        return ApiResponse::success(new DepartmentResource($department), 'Department updated successfully.');
    }

    /**
     * Delete department if no employees belong to it.
     */
    public function destroy(Request $request, Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        if ($department->employees()->count() > 0) {
            return ApiResponse::error(
                'DEPARTMENT_HAS_EMPLOYEES',
                'Cannot delete department because employees are currently assigned to it.',
                422
            );
        }

        /** @var User $actor */
        $actor = $request->user();
        $old = $department->toArray();

        $department->delete();

        $this->auditService->log('department.deleted', null, $old, null, $actor);

        return ApiResponse::success(null, 'Department deleted successfully.');
    }
}
