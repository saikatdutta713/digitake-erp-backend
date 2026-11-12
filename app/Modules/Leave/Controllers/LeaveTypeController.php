<?php

namespace App\Modules\Leave\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Leave\Requests\CreateLeaveTypeRequest;
use App\Modules\Leave\Requests\UpdateLeaveTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveTypeController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of leave categories & structure.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        LeaveType::ensureDefaultTypesExist();

        $user = $request->user();
        $isElevated = $user && ($user->isSuperAdmin() || $user->can('leaves.manage'));

        $query = LeaveType::query()->latest('id');

        if (! $isElevated) {
            $query->active();
        } elseif ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $types = $query->get();

        $totalPaidQuota = (float) LeaveType::where('is_active', true)->where('is_paid', true)->sum('days_per_year');
        $halfDayCount = LeaveType::where('is_active', true)->where('allow_half_day', true)->count();
        $approvalRequiredCount = LeaveType::where('is_active', true)->where('requires_approval', true)->count();

        return LeaveTypeResource::collection($types)->additional([
            'meta' => [
                'total_categories_count' => LeaveType::count(),
                'active_categories_count' => LeaveType::where('is_active', true)->count(),
                'total_annual_paid_quota' => $totalPaidQuota,
                'half_day_enabled_count' => $halfDayCount,
                'approval_required_count' => $approvalRequiredCount,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
            ],
        ]);
    }

    /**
     * Store a newly created custom leave category.
     */
    public function store(CreateLeaveTypeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by_id'] = $request->user()->id;

        $leaveType = LeaveType::create($validated);

        $this->auditService->log(
            action: 'leave_type.created',
            entity: $leaveType,
            oldValues: null,
            newValues: $validated
        );

        return (new LeaveTypeResource($leaveType))
            ->additional(['message' => 'Custom leave category successfully created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified leave category.
     */
    public function show(LeaveType $leaveType): LeaveTypeResource
    {
        return new LeaveTypeResource($leaveType);
    }

    /**
     * Update the specified leave category.
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $validated = $request->validated();
        $oldDays = $leaveType->days_per_year;

        $leaveType->update($validated);

        $this->auditService->log(
            action: 'leave_type.updated',
            entity: $leaveType,
            oldValues: ['days_per_year' => $oldDays],
            newValues: $validated
        );

        return (new LeaveTypeResource($leaveType))
            ->additional(['message' => 'Leave category successfully updated.'])
            ->response();
    }

    /**
     * Remove the specified leave category from storage.
     */
    public function destroy(Request $request, LeaveType $leaveType): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->can('leaves.manage'))) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $typeName = $leaveType->name;

        $leaveType->delete();

        $this->auditService->log(
            action: 'leave_type.deleted',
            entity: $leaveType
        );

        return response()->json([
            'message' => "Leave category '{$typeName}' was successfully deleted.",
        ]);
    }

    /**
     * Reset leave structure back to global standards.
     */
    public function resetDefaults(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admin can reset leave structure to defaults.'], 403);
        }

        foreach (LeaveType::defaultTypes() as $spec) {
            LeaveType::withTrashed()->updateOrCreate(
                ['code' => $spec['code']],
                array_merge($spec, ['deleted_at' => null])
            );
        }

        $this->auditService->log(
            action: 'leave_type.reset_defaults',
            entity: null,
            oldValues: null,
            newValues: ['reset_by' => $user->id]
        );

        return response()->json([
            'message' => 'Leave structure restored to global enterprise defaults.',
            'categories' => LeaveTypeResource::collection(LeaveType::active()->get()),
        ]);
    }
}
