<?php

namespace App\Modules\Leave\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveResource;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Leave\Requests\ApplyLeaveRequest;
use App\Modules\Leave\Requests\RejectLeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of leave applications with statistical metrics.
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $user = $request->user();
        $isElevated = $user->isSuperAdmin() || $user->can('leaves.manage') || $user->can('leaves.view');

        $query = Leave::with(['user', 'employee.department', 'appliedBy', 'approvedBy', 'actingDelegate'])
            ->latest('id');

        // View Mode Filter
        $view = $request->query('view', $isElevated ? 'all' : 'my');
        if ($view === 'my' || ! $isElevated) {
            $query->where('user_id', $user->id);
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Leave Type Filter
        if ($request->filled('leave_type') && $request->leave_type !== 'all') {
            $query->where('leave_type', $request->leave_type);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('leave_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('employee_code', 'like', "%{$search}%");
                    });
            });
        }

        $leaves = $query->paginate($request->integer('per_page', 25));

        // Statistical metadata
        $today = Carbon::today()->toDateString();
        $pendingApprovals = Leave::where('status', 'pending')->count();
        $currentlyOnLeaveToday = Leave::where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        // Calculate dynamic personal balance for requesting user based on active policy
        LeaveType::ensureDefaultTypesExist();
        $currentYear = Carbon::now()->year;
        $totalPaidPolicy = (float) LeaveType::where('is_active', true)->where('is_paid', true)->sum('days_per_year');
        if ($totalPaidPolicy <= 0) {
            $totalPaidPolicy = 18.0;
        }

        $usedDaysThisYear = (float) Leave::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('days');
        $availableBalance = max(0, $totalPaidPolicy - $usedDaysThisYear);

        return LeaveResource::collection($leaves)->additional([
            'meta' => [
                'annual_entitlement_policy' => $totalPaidPolicy,
                'pending_approvals_count' => $pendingApprovals,
                'currently_on_leave_today_count' => $currentlyOnLeaveToday,
                'user_available_pool_balance' => $availableBalance,
                'user_used_days_this_year' => $usedDaysThisYear,
                'is_super_admin' => $user->isSuperAdmin(),
            ],
        ]);
    }

    /**
     * Get entitlement and quota balance for the user or a target employee.
     */
    public function balances(Request $request): JsonResponse
    {
        $user = $request->user();
        $targetUserId = $request->query('user_id', $user->id);

        if ($targetUserId != $user->id && ! $user->isSuperAdmin() && ! $user->can('leaves.manage')) {
            return response()->json(['message' => 'Unauthorized to view external balances.'], 403);
        }

        $targetUser = User::with('employee')->findOrFail($targetUserId);
        $currentYear = Carbon::now()->year;

        LeaveType::ensureDefaultTypesExist();
        $activeTypes = LeaveType::where('is_active', true)->get();
        $entitlements = [];

        foreach ($activeTypes as $lt) {
            $used = (float) Leave::where('user_id', $targetUser->id)
                ->where('leave_type', $lt->code)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('days');

            $entitlements[$lt->code] = [
                'id' => $lt->id,
                'name' => $lt->name,
                'code' => $lt->code,
                'allocated' => (float) $lt->days_per_year,
                'used' => $used,
                'remaining' => max(0, (float) $lt->days_per_year - $used),
                'is_paid' => (bool) $lt->is_paid,
                'color' => $lt->color ?? '#10b981',
                'allow_half_day' => (bool) $lt->allow_half_day,
                'requires_approval' => (bool) $lt->requires_approval,
            ];
        }

        return response()->json([
            'year' => $currentYear,
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'is_super_admin' => $targetUser->isSuperAdmin(),
            ],
            'entitlements' => $entitlements,
            'executive_exemption' => $targetUser->isSuperAdmin(),
        ]);
    }

    /**
     * Store a newly applied leave request.
     * Incorporates Super Admin executive auto-approval & proxy submissions.
     */
    public function store(ApplyLeaveRequest $request): JsonResponse
    {
        $actor = $request->user();
        $validated = $request->validated();

        // Determine applicant identity (Self vs Proxy)
        $targetUserId = $validated['target_user_id'] ?? $actor->id;
        $isProxy = (int) $targetUserId !== (int) $actor->id;

        if ($isProxy && ! $actor->isSuperAdmin() && ! $actor->can('leaves.manage')) {
            return response()->json([
                'message' => 'You do not have administrative permission to apply leave on behalf of other staff.',
            ], 403);
        }

        $targetUser = User::with('employee')->findOrFail($targetUserId);

        // Calculate leave duration & check dynamic leave type policy
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $halfDayType = $validated['half_day_type'] ?? 'none';

        $leaveTypeRecord = LeaveType::where('code', $validated['leave_type'])->first();
        if ($leaveTypeRecord && $halfDayType !== 'none' && ! $leaveTypeRecord->allow_half_day) {
            return response()->json([
                'message' => "Half day leave is not permitted for {$leaveTypeRecord->name}.",
                'errors' => ['half_day_type' => ["Half day leave is disabled for {$leaveTypeRecord->name}."]],
            ], 422);
        }

        if ($halfDayType !== 'none') {
            $days = 0.5;
        } elseif (! empty($validated['days'])) {
            $days = (float) $validated['days'];
        } else {
            $days = (float) ($startDate->diffInDays($endDate) + 1);
        }

        // =========================================================================
        // INDUSTRY ENTERPRISE RULE: SUPER ADMIN & EXECUTIVE AUTO-APPROVAL WORKFLOW
        // =========================================================================
        // If the target user is Super Admin, their leave is self-exempt and automatically
        // approved without managerial gatekeeping, as root authority has no superiors.
        $isSuperAdminPersonal = $targetUser->isSuperAdmin();
        $isAdminOverrideApprove = $isProxy && ($validated['auto_approve'] ?? false) && ($actor->isSuperAdmin() || $actor->can('leaves.approve'));

        if ($isSuperAdminPersonal) {
            $status = 'approved';
            $isAutoApproved = true;
            $approvedById = $actor->id;
            $actionLog = 'LEAVE_AUTO_APPROVED';
        } elseif ($isAdminOverrideApprove) {
            $status = 'approved';
            $isAutoApproved = false;
            $approvedById = $actor->id;
            $actionLog = 'LEAVE_PROXY_APPROVED';
        } else {
            $status = 'pending';
            $isAutoApproved = false;
            $approvedById = null;
            $actionLog = 'LEAVE_APPLIED';
        }

        $leave = Leave::create([
            'user_id' => $targetUser->id,
            'employee_id' => $targetUser->employee?->id,
            'applied_by_id' => $actor->id,
            'approved_by_id' => $approvedById,
            'acting_delegate_id' => $validated['acting_delegate_id'] ?? null,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days' => $days,
            'half_day_type' => $halfDayType,
            'reason' => $validated['reason'],
            'status' => $status,
            'is_auto_approved' => $isAutoApproved,
        ]);

        $leave->load(['user', 'employee.department', 'appliedBy', 'approvedBy', 'actingDelegate']);

        // Record compliance audit log
        $this->auditService->log(
            action: $actionLog,
            entity: $leave,
            oldValues: null,
            newValues: [
                'leave_id' => $leave->id,
                'target_user' => $targetUser->name,
                'leave_type' => $leave->leave_type,
                'days' => $days,
                'status' => $status,
                'is_auto_approved' => $isAutoApproved,
                'is_proxy' => $isProxy,
                'acting_delegate_id' => $leave->acting_delegate_id,
            ],
            actor: $actor
        );

        return (new LeaveResource($leave))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Approve a pending leave application.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isSuperAdmin() && ! $actor->can('leaves.approve')) {
            return response()->json(['message' => 'Unauthorized to approve leave requests.'], 403);
        }

        $leave = Leave::with(['user', 'employee'])->findOrFail($id);

        if ($leave->status === 'approved') {
            return response()->json(['message' => 'Leave application is already approved.'], 422);
        }

        $oldStatus = $leave->status;
        $leave->update([
            'status' => 'approved',
            'approved_by_id' => $actor->id,
            'rejection_reason' => null,
        ]);

        $leave->load(['user', 'employee.department', 'appliedBy', 'approvedBy', 'actingDelegate']);

        $this->auditService->log(
            action: 'LEAVE_APPROVED',
            entity: $leave,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'approved', 'approved_by_id' => $actor->id],
            actor: $actor
        );

        return response()->json([
            'message' => 'Leave application approved successfully.',
            'data' => new LeaveResource($leave),
        ]);
    }

    /**
     * Reject a pending leave application with mandatory reason.
     */
    public function reject(RejectLeaveRequest $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isSuperAdmin() && ! $actor->can('leaves.reject')) {
            return response()->json(['message' => 'Unauthorized to reject leave requests.'], 403);
        }

        $leave = Leave::with(['user', 'employee'])->findOrFail($id);

        if ($leave->is_auto_approved && ! $actor->isSuperAdmin()) {
            return response()->json(['message' => 'Executive auto-approved leave cannot be rejected by non-superadmins.'], 403);
        }

        $oldStatus = $leave->status;
        $leave->update([
            'status' => 'rejected',
            'approved_by_id' => $actor->id,
            'rejection_reason' => $request->rejection_reason,
        ]);

        $leave->load(['user', 'employee.department', 'appliedBy', 'approvedBy', 'actingDelegate']);

        $this->auditService->log(
            action: 'LEAVE_REJECTED',
            entity: $leave,
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'rejected_by_id' => $actor->id,
            ],
            actor: $actor
        );

        return response()->json([
            'message' => 'Leave application has been rejected.',
            'data' => new LeaveResource($leave),
        ]);
    }

    /**
     * Cancel an existing leave application.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $leave = Leave::findOrFail($id);

        if ($leave->user_id !== $actor->id && ! $actor->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized to cancel this leave application.'], 403);
        }

        if ($leave->status === 'cancelled') {
            return response()->json(['message' => 'Leave is already cancelled.'], 422);
        }

        $oldStatus = $leave->status;
        $leave->update(['status' => 'cancelled']);

        $this->auditService->log(
            action: 'LEAVE_CANCELLED',
            entity: $leave,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'cancelled'],
            actor: $actor
        );

        return response()->json([
            'message' => 'Leave application has been cancelled.',
            'data' => new LeaveResource($leave),
        ]);
    }
}
