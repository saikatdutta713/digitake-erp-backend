<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Get the logged in user's today attendance status & company policy.
     */
    public function today(Request $request): JsonResponse
    {
        $status = $this->attendanceService->getTodayStatus($request->user());

        return response()->json([
            'status' => 'success',
            'data' => $status,
        ]);
    }

    /**
     * User clock in.
     */
    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modality' => ['nullable', 'string', 'in:office,wfh,hybrid'],
            'device_uuid' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance = $this->attendanceService->clockIn($request->user(), $validated, $request->ip());

        return response()->json([
            'status' => 'success',
            'message' => 'Clock-in recorded successfully.',
            'data' => $attendance,
        ]);
    }

    /**
     * User clock out.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance = $this->attendanceService->clockOut($request->user(), $validated['remarks'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Clock-out recorded successfully.',
            'data' => $attendance,
        ]);
    }

    /**
     * Start break.
     */
    public function startBreak(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'break_type' => ['nullable', 'string', 'max:50'],
        ]);

        $attendance = $this->attendanceService->startBreak($request->user(), $validated['break_type'] ?? 'lunch');

        return response()->json([
            'status' => 'success',
            'message' => 'Break started.',
            'data' => $attendance,
        ]);
    }

    /**
     * End break.
     */
    public function endBreak(Request $request): JsonResponse
    {
        $attendance = $this->attendanceService->endBreak($request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Break ended. Resumed active status.',
            'data' => $attendance,
        ]);
    }

    /**
     * High-frequency background telemetry sync from Electron desktop agent.
     */
    public function recordTelemetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intervals' => ['required', 'array'],
            'intervals.*.interval_start' => ['nullable', 'string'],
            'intervals.*.interval_end' => ['nullable', 'string'],
            'intervals.*.active_seconds' => ['nullable', 'integer', 'min:0'],
            'intervals.*.idle_seconds' => ['nullable', 'integer', 'min:0'],
            'intervals.*.break_seconds' => ['nullable', 'integer', 'min:0'],
            'intervals.*.state' => ['nullable', 'string', 'in:active,idle,break'],
            'intervals.*.break_type' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->attendanceService->recordTelemetry(
            $request->user(),
            $validated['intervals'],
            $request->ip()
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Attendance roster listing.
     */
    public function roster(Request $request): JsonResponse
    {
        $roster = $this->attendanceService->getRoster($request);

        return response()->json([
            'status' => 'success',
            'data' => $roster,
        ]);
    }

    /**
     * Summary metrics for dashboard tiles.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->attendanceService->getStats($request);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Administrative manual punch override.
     */
    public function manualPunch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasRole('HR Manager') && ! $user->hasRole('Department Manager') && ! $user->can('attendance.create')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to perform administrative attendance override.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'clock_in_at' => ['required', 'string'],
            'clock_out_at' => ['nullable', 'string'],
            'modality' => ['nullable', 'string', 'in:office,wfh,hybrid'],
            'status' => ['nullable', 'string', 'in:present,late,half_day,absent,on_leave,on_duty'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'active_minutes' => ['nullable', 'integer', 'min:0'],
            'idle_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $attendance = $this->attendanceService->manualPunch($validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Manual punch override recorded successfully.',
            'data' => $attendance,
        ]);
    }

    /**
     * Adjust an existing attendance record.
     */
    public function adjustRecord(Request $request, Attendance $attendance): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasRole('HR Manager') && ! $user->hasRole('Department Manager') && ! $user->can('attendance.update')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to adjust attendance records.',
            ], 403);
        }

        $validated = $request->validate([
            'clock_in_at' => ['nullable', 'string'],
            'clock_out_at' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:present,late,half_day,absent,on_leave,on_duty'],
            'modality' => ['nullable', 'string', 'in:office,wfh,hybrid'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'active_minutes' => ['nullable', 'integer', 'min:0'],
            'idle_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $updated = $this->attendanceService->adjustRecord($attendance, $validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance record adjusted successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Get attendance policy.
     */
    public function getPolicy(): JsonResponse
    {
        $policy = $this->attendanceService->getPolicy();

        return response()->json([
            'status' => 'success',
            'data' => $policy,
        ]);
    }

    /**
     * Update attendance policy.
     */
    public function updatePolicy(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasRole('HR Manager')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to update company attendance policy.',
            ], 403);
        }

        $validated = $request->validate([
            'standard_start_time' => ['nullable', 'string', 'max:10'],
            'standard_end_time' => ['nullable', 'string', 'max:10'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'half_day_threshold_minutes' => ['nullable', 'integer', 'min:60', 'max:600'],
            'full_day_threshold_minutes' => ['nullable', 'integer', 'min:120', 'max:1200'],
            'idle_timeout_seconds' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'allowed_modalities' => ['nullable', 'array'],
            'office_ips' => ['nullable', 'array'],
            'auto_clock_out_at' => ['nullable', 'string', 'max:10'],
        ]);

        $policy = $this->attendanceService->updatePolicy($validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance policy updated successfully.',
            'data' => $policy,
        ]);
    }
}
