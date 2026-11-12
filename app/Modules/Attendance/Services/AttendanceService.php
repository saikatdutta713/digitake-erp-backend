<?php

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Models\AttendanceActivity;
use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Clock in the user for today.
     *
     * @param  array<string, mixed>  $data
     */
    public function clockIn(User $user, array $data = [], ?string $ip = null): Attendance
    {
        $today = Carbon::today()->toDateString();
        $policy = AttendancePolicy::getActivePolicy();

        $existing = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $modality = $data['modality'] ?? 'office';
        if (! in_array($modality, ['office', 'wfh', 'hybrid'], true)) {
            $modality = 'office';
        }

        $deviceUuid = $data['device_uuid'] ?? null;
        $now = Carbon::now();

        if ($existing) {
            // Already clocked in today
            if ($existing->clock_out_at !== null) {
                // Resuming after clock out
                $existing->update([
                    'clock_out_at' => null,
                    'modality' => $modality,
                    'device_uuid' => $deviceUuid ?? $existing->device_uuid,
                    'ip_address' => $ip ?? $existing->ip_address,
                ]);

                $this->auditService->log(
                    'attendance.clock_in_resume',
                    $existing,
                    null,
                    ['clock_in_at' => $existing->clock_in_at, 'resumed_at' => $now->toDateTimeString()],
                    $user
                );
            }

            return $existing->load(['user', 'employee.department', 'employee.designation']);
        }

        // Determine if late
        $standardStart = Carbon::createFromTimeString($policy->standard_start_time);
        $lateThreshold = $standardStart->copy()->addMinutes($policy->late_grace_minutes);
        $currentTimeToday = Carbon::createFromTimeString($now->toTimeString());

        $status = $currentTimeToday->greaterThan($lateThreshold) ? 'late' : 'present';

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'date' => $today,
            'clock_in_at' => $now,
            'clock_out_at' => null,
            'total_duration_minutes' => 0,
            'active_minutes' => 0,
            'idle_minutes' => 0,
            'break_minutes' => 0,
            'productivity_score' => 100.00,
            'modality' => $modality,
            'status' => $status,
            'device_uuid' => $deviceUuid,
            'ip_address' => $ip,
            'remarks' => $data['remarks'] ?? null,
            'is_manual_override' => false,
        ]);

        $this->auditService->log(
            'attendance.clock_in',
            $attendance,
            null,
            [
                'clock_in_at' => $now->toDateTimeString(),
                'modality' => $modality,
                'status' => $status,
                'device_uuid' => $deviceUuid,
            ],
            $user
        );

        return $attendance->load(['user', 'employee.department', 'employee.designation']);
    }

    /**
     * Clock out the user for today.
     */
    public function clockOut(User $user, ?string $remarks = null): Attendance
    {
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out_at')
            ->first();

        if (! $attendance) {
            $latest = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->first();

            if ($latest) {
                return $latest->load(['user', 'employee.department', 'employee.designation']);
            }

            throw ValidationException::withMessages([
                'attendance' => ['No active clock-in found for today.'],
            ]);
        }

        $now = Carbon::now();
        $attendance->clock_out_at = $now;

        if ($remarks) {
            $attendance->remarks = trim(($attendance->remarks ? $attendance->remarks.' | ' : '').$remarks);
        }

        // Recalculate duration & metrics
        $attendance->save();
        $attendance->recalculateMetrics();

        // Check if half day threshold met
        $policy = AttendancePolicy::getActivePolicy();
        if ($attendance->active_minutes < $policy->half_day_threshold_minutes && $attendance->status === 'present') {
            $attendance->status = 'half_day';
            $attendance->save();
        }

        $this->auditService->log(
            'attendance.clock_out',
            $attendance,
            null,
            [
                'clock_out_at' => $now->toDateTimeString(),
                'active_minutes' => $attendance->active_minutes,
                'idle_minutes' => $attendance->idle_minutes,
                'productivity_score' => $attendance->productivity_score,
            ],
            $user
        );

        return $attendance->load(['user', 'employee.department', 'employee.designation']);
    }

    /**
     * Start a break for the user.
     */
    public function startBreak(User $user, string $type = 'lunch'): Attendance
    {
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out_at')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'attendance' => ['User must be clocked in before starting a break.'],
            ]);
        }

        AttendanceActivity::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'interval_start' => Carbon::now(),
            'interval_end' => Carbon::now(),
            'active_seconds' => 0,
            'idle_seconds' => 0,
            'break_seconds' => 0,
            'state' => 'break',
            'break_type' => $type,
            'source' => 'desktop_agent',
        ]);

        return $attendance->load(['user', 'employee.department', 'employee.designation']);
    }

    /**
     * End current break for the user.
     */
    public function endBreak(User $user): Attendance
    {
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out_at')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'attendance' => ['No active attendance session.'],
            ]);
        }

        $latestBreak = AttendanceActivity::where('attendance_id', $attendance->id)
            ->where('state', 'break')
            ->latest('id')
            ->first();

        if ($latestBreak && $latestBreak->interval_end->equalTo($latestBreak->interval_start)) {
            $now = Carbon::now();
            $durationSec = max(0, $now->diffInSeconds($latestBreak->interval_start));
            $latestBreak->update([
                'interval_end' => $now,
                'break_seconds' => $durationSec,
            ]);

            $attendance->recalculateMetrics();
        }

        return $attendance->load(['user', 'employee.department', 'employee.designation']);
    }

    /**
     * High-throughput telemetry ingestion from Desktop agents.
     *
     * @param  array<int, array<string, mixed>>  $intervals
     * @return array<string, mixed>
     */
    public function recordTelemetry(User $user, array $intervals, ?string $ip = null): array
    {
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (! $attendance) {
            // Auto clock-in if first activity of day arrived
            $attendance = $this->clockIn($user, ['modality' => 'office'], $ip);
        }

        $recordsToInsert = [];
        $totalActiveSec = 0;
        $totalIdleSec = 0;
        $totalBreakSec = 0;

        foreach ($intervals as $item) {
            $start = isset($item['interval_start']) ? Carbon::parse($item['interval_start']) : Carbon::now();
            $end = isset($item['interval_end']) ? Carbon::parse($item['interval_end']) : Carbon::now();
            $active = (int) ($item['active_seconds'] ?? 0);
            $idle = (int) ($item['idle_seconds'] ?? 0);
            $break = (int) ($item['break_seconds'] ?? 0);
            $state = $item['state'] ?? ($break > 0 ? 'break' : ($idle > $active ? 'idle' : 'active'));
            $breakType = $item['break_type'] ?? null;

            $totalActiveSec += $active;
            $totalIdleSec += $idle;
            $totalBreakSec += $break;

            $recordsToInsert[] = [
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'interval_start' => $start,
                'interval_end' => $end,
                'active_seconds' => $active,
                'idle_seconds' => $idle,
                'break_seconds' => $break,
                'state' => $state,
                'break_type' => $breakType,
                'source' => 'desktop_agent',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        if (! empty($recordsToInsert)) {
            AttendanceActivity::insert($recordsToInsert);
        }

        $attendance->recalculateMetrics();

        return [
            'success' => true,
            'attendance_id' => $attendance->id,
            'active_minutes' => $attendance->active_minutes,
            'idle_minutes' => $attendance->idle_minutes,
            'break_minutes' => $attendance->break_minutes,
            'productivity_score' => $attendance->productivity_score,
            'status' => $attendance->status,
        ];
    }

    /**
     * Get the current user's today attendance status and policy.
     *
     * @return array<string, mixed>
     */
    public function getTodayStatus(User $user): array
    {
        $today = Carbon::today()->toDateString();
        $attendance = Attendance::with(['employee.department', 'employee.designation'])
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $policy = AttendancePolicy::getActivePolicy();

        $isClockedIn = $attendance !== null && $attendance->clock_out_at === null;

        $lastActivity = null;
        $isOnBreak = false;
        $currentBreakType = null;

        if ($attendance) {
            $lastActivity = AttendanceActivity::where('attendance_id', $attendance->id)
                ->latest('id')
                ->first();

            if ($lastActivity && $lastActivity->state === 'break' && $lastActivity->interval_end->equalTo($lastActivity->interval_start)) {
                $isOnBreak = true;
                $currentBreakType = $lastActivity->break_type;
            }
        }

        return [
            'is_clocked_in' => $isClockedIn,
            'is_on_break' => $isOnBreak,
            'current_break_type' => $currentBreakType,
            'attendance' => $attendance,
            'policy' => [
                'standard_start_time' => $policy->standard_start_time,
                'standard_end_time' => $policy->standard_end_time,
                'idle_timeout_seconds' => $policy->idle_timeout_seconds,
                'allowed_modalities' => $policy->allowed_modalities ?? ['office', 'wfh', 'hybrid'],
            ],
        ];
    }

    /**
     * Query roster listing for a given date.
     *
     * @return array<string, mixed>
     */
    public function getRoster(Request $request): array
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $query = Attendance::with([
            'user',
            'employee.department',
            'employee.designation',
            'overrideBy',
        ])
            ->where('date', $date)
            ->latest('clock_in_at');

        if ($request->filled('department_id') && $request->department_id !== 'all') {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('modality') && $request->modality !== 'all') {
            $query->where('modality', $request->modality);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('employee', function ($eq) use ($search) {
                    $eq->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            });
        }

        $attendances = $query->get();

        return [
            'date' => $date,
            'count' => $attendances->count(),
            'records' => $attendances,
        ];
    }

    /**
     * Compute statistics for attendance summary tiles.
     *
     * @return array<string, mixed>
     */
    public function getStats(Request $request): array
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $totalEmployees = Employee::where('employment_status', 'active')->count();
        if ($totalEmployees === 0) {
            $totalEmployees = User::where('status', 'active')->count();
        }

        $records = Attendance::where('date', $date)->get();

        $presentCount = $records->whereIn('status', ['present', 'late', 'half_day'])->count();
        $lateCount = $records->where('status', 'late')->count();
        $halfDayCount = $records->where('status', 'half_day')->count();
        $absentCount = max(0, $totalEmployees - $presentCount);

        $officeCount = $records->where('modality', 'office')->count();
        $wfhCount = $records->where('modality', 'wfh')->count();
        $hybridCount = $records->where('modality', 'hybrid')->count();

        $avgProductivity = $records->isNotEmpty()
            ? round($records->avg('productivity_score'), 1)
            : 100.0;

        $totalActiveMins = $records->sum('active_minutes');
        $totalIdleMins = $records->sum('idle_minutes');
        $totalBreakMins = $records->sum('break_minutes');

        return [
            'date' => $date,
            'total_headcount' => $totalEmployees,
            'present_count' => $presentCount,
            'late_count' => $lateCount,
            'half_day_count' => $halfDayCount,
            'absent_count' => $absentCount,
            'office_count' => $officeCount,
            'wfh_count' => $wfhCount,
            'hybrid_count' => $hybridCount,
            'average_productivity' => $avgProductivity,
            'total_active_hours' => round($totalActiveMins / 60, 1),
            'total_idle_hours' => round($totalIdleMins / 60, 1),
            'total_break_hours' => round($totalBreakMins / 60, 1),
            'attendance_rate' => $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100, 1) : 0,
        ];
    }

    /**
     * Administrative manual punch override.
     *
     * @param  array<string, mixed>  $data
     */
    public function manualPunch(array $data, User $actor): Attendance
    {
        $userId = $data['user_id'] ?? null;
        $employeeId = $data['employee_id'] ?? null;

        if (! $userId && $employeeId) {
            $employee = Employee::find($employeeId);
            $userId = $employee?->user_id;
        }

        if (! $userId) {
            throw ValidationException::withMessages([
                'user_id' => ['Target employee or user is required for manual punch override.'],
            ]);
        }

        $date = $data['date'] ?? Carbon::today()->toDateString();
        $clockIn = isset($data['clock_in_at']) ? Carbon::parse($data['clock_in_at']) : Carbon::now();
        $clockOut = isset($data['clock_out_at']) ? Carbon::parse($data['clock_out_at']) : null;

        $attendance = Attendance::updateOrCreate(
            ['user_id' => $userId, 'date' => $date],
            [
                'employee_id' => $employeeId ?? User::find($userId)?->employee?->id,
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'modality' => $data['modality'] ?? 'office',
                'status' => $data['status'] ?? 'present',
                'remarks' => $data['remarks'] ?? 'Manual administrative punch override',
                'is_manual_override' => true,
                'override_by_user_id' => $actor->id,
            ]
        );

        if ($clockOut) {
            $attendance->total_duration_minutes = max(0, $clockIn->diffInMinutes($clockOut));
            $attendance->active_minutes = $data['active_minutes'] ?? $attendance->total_duration_minutes;
            $attendance->idle_minutes = $data['idle_minutes'] ?? 0;
            $attendance->break_minutes = $data['break_minutes'] ?? 0;
            $attendance->productivity_score = 100.00;
            $attendance->save();
        }

        $this->auditService->log(
            'attendance.manual_punch_override',
            $attendance,
            null,
            $attendance->toArray(),
            $actor
        );

        return $attendance->load(['user', 'employee.department', 'employee.designation', 'overrideBy']);
    }

    /**
     * Adjust an existing attendance record.
     *
     * @param  array<string, mixed>  $data
     */
    public function adjustRecord(Attendance $attendance, array $data, User $actor): Attendance
    {
        $oldValues = $attendance->toArray();

        if (isset($data['clock_in_at'])) {
            $attendance->clock_in_at = Carbon::parse($data['clock_in_at']);
        }
        if (array_key_exists('clock_out_at', $data)) {
            $attendance->clock_out_at = $data['clock_out_at'] ? Carbon::parse($data['clock_out_at']) : null;
        }
        if (isset($data['status'])) {
            $attendance->status = $data['status'];
        }
        if (isset($data['modality'])) {
            $attendance->modality = $data['modality'];
        }
        if (isset($data['remarks'])) {
            $attendance->remarks = $data['remarks'];
        }
        if (isset($data['active_minutes'])) {
            $attendance->active_minutes = (int) $data['active_minutes'];
        }
        if (isset($data['idle_minutes'])) {
            $attendance->idle_minutes = (int) $data['idle_minutes'];
        }

        if ($attendance->clock_out_at && $attendance->clock_in_at) {
            $attendance->total_duration_minutes = max(0, $attendance->clock_in_at->diffInMinutes($attendance->clock_out_at));
            $workMin = $attendance->active_minutes + $attendance->idle_minutes;
            if ($workMin > 0) {
                $attendance->productivity_score = round(($attendance->active_minutes / $workMin) * 100, 2);
            }
        }

        $attendance->is_manual_override = true;
        $attendance->override_by_user_id = $actor->id;
        $attendance->save();

        $this->auditService->log(
            'attendance.record_adjusted',
            $attendance,
            $oldValues,
            $attendance->toArray(),
            $actor
        );

        return $attendance->load(['user', 'employee.department', 'employee.designation', 'overrideBy']);
    }

    /**
     * Retrieve attendance policy.
     */
    public function getPolicy(): AttendancePolicy
    {
        return AttendancePolicy::getActivePolicy();
    }

    /**
     * Update attendance policy.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePolicy(array $data, User $actor): AttendancePolicy
    {
        $policy = AttendancePolicy::getActivePolicy();
        $old = $policy->toArray();

        $policy->update($data);

        $this->auditService->log(
            'attendance.policy_updated',
            $policy,
            $old,
            $policy->toArray(),
            $actor
        );

        return $policy;
    }
}
