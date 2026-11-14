<?php

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Models\AttendanceActivity;
use App\Models\AttendancePolicy;
use App\Models\DeviceSession;
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

    /**
     * Compile detailed tracking telemetry, aggregate statistics, timeline series,
     * granular telemetry intervals, and workstation bindings for a specific employee.
     *
     * @return array<string, mixed>
     */
    public function getEmployeeTrackingData(Employee $employee, Request $request): array
    {
        $period = (string) $request->query('period', '30d');
        $startDateStr = $request->query('start_date');
        $endDateStr = $request->query('end_date');
        $modalityFilter = $request->query('modality');

        $today = Carbon::today();

        if ($startDateStr && $endDateStr) {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
            $endDate = Carbon::parse($endDateStr)->endOfDay();
        } else {
            switch ($period) {
                case 'today':
                    $startDate = $today->copy()->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case '7d':
                    $startDate = $today->copy()->subDays(6)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case 'this_month':
                    $startDate = $today->copy()->startOfMonth()->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case 'last_month':
                    $startDate = $today->copy()->subMonth()->startOfMonth()->startOfDay();
                    $endDate = $today->copy()->subMonth()->endOfMonth()->endOfDay();
                    break;
                case 'all':
                    $startDate = Carbon::create(2020, 1, 1);
                    $endDate = $today->copy()->endOfDay();
                    break;
                case '30d':
                default:
                    $startDate = $today->copy()->subDays(29)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
            }
        }

        $userId = $employee->user_id;

        // Load employee relationships
        $employee->load([
            'department:id,name,code',
            'designation:id,name,code',
            'reportingManager:id,first_name,last_name,employee_code',
            'user:id,name,email,status',
            'user.roles:id,name',
        ]);

        // Query attendances in date range
        $query = Attendance::with(['overrideBy:id,name,email'])
            ->where(function ($q) use ($employee, $userId) {
                $q->where('employee_id', $employee->id);
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($modalityFilter && in_array($modalityFilter, ['office', 'wfh', 'hybrid'], true)) {
            $query->where('modality', $modalityFilter);
        }

        $records = $query->orderBy('date', 'desc')->get();

        // Aggregates & Statistics
        $totalDays = $records->count();
        $totalDurationMinutes = (int) $records->sum('total_duration_minutes');
        $totalActiveMinutes = (int) $records->sum('active_minutes');
        $totalIdleMinutes = (int) $records->sum('idle_minutes');
        $totalBreakMinutes = (int) $records->sum('break_minutes');

        $totalWorkMinutes = $totalActiveMinutes + $totalIdleMinutes;
        $averageProductivity = $totalWorkMinutes > 0
            ? round(($totalActiveMinutes / $totalWorkMinutes) * 100, 1)
            : ($totalDays > 0 ? round((float) ($records->avg('productivity_score') ?? 0), 1) : 0);

        $presentCount = $records->where('status', 'present')->count();
        $lateCount = $records->where('status', 'late')->count();
        $halfDayCount = $records->where('status', 'half_day')->count();
        $absentCount = $records->where('status', 'absent')->count();
        $onLeaveCount = $records->where('status', 'on_leave')->count();

        $punctualityRate = $totalDays > 0 ? round(($presentCount / $totalDays) * 100, 1) : 0;

        $officeCount = $records->where('modality', 'office')->count();
        $wfhCount = $records->where('modality', 'wfh')->count();
        $hybridCount = $records->where('modality', 'hybrid')->count();

        // Today status check
        $todayRecord = Attendance::where(function ($q) use ($employee, $userId) {
            $q->where('employee_id', $employee->id);
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        })->where('date', $today->toDateString())->first();

        $liveState = 'offline';
        if ($todayRecord) {
            if ($todayRecord->clock_out_at !== null) {
                $liveState = 'clocked_out';
            } else {
                $lastActivity = AttendanceActivity::where('attendance_id', $todayRecord->id)
                    ->orderByDesc('interval_start')
                    ->first();
                $liveState = $lastActivity?->state ?? 'active';
            }
        }

        // Daily timeline formatting for visual charts (sorted chronologically)
        $timeline = $records->sortBy('date')->values()->map(function (Attendance $att) {
            $durationHours = round($att->total_duration_minutes / 60, 2);
            $activeHours = round($att->active_minutes / 60, 2);
            $idleHours = round($att->idle_minutes / 60, 2);
            $breakHours = round($att->break_minutes / 60, 2);

            return [
                'id' => $att->id,
                'date' => $att->date->toDateString(),
                'formatted_date' => Carbon::parse($att->date)->format('M d, Y'),
                'day_name' => Carbon::parse($att->date)->format('D'),
                'clock_in_at' => $att->clock_in_at ? Carbon::parse($att->clock_in_at)->toIso8601String() : null,
                'clock_out_at' => $att->clock_out_at ? Carbon::parse($att->clock_out_at)->toIso8601String() : null,
                'clock_in_time' => $att->clock_in_at ? Carbon::parse($att->clock_in_at)->format('H:i') : null,
                'clock_out_time' => $att->clock_out_at ? Carbon::parse($att->clock_out_at)->format('H:i') : null,
                'duration_minutes' => $att->total_duration_minutes,
                'duration_hours' => $durationHours,
                'active_minutes' => $att->active_minutes,
                'active_hours' => $activeHours,
                'idle_minutes' => $att->idle_minutes,
                'idle_hours' => $idleHours,
                'break_minutes' => $att->break_minutes,
                'break_hours' => $breakHours,
                'productivity_score' => (float) $att->productivity_score,
                'status' => $att->status,
                'modality' => $att->modality,
                'is_manual_override' => (bool) $att->is_manual_override,
                'override_by' => $att->overrideBy ? ['id' => $att->overrideBy->id, 'name' => $att->overrideBy->name] : null,
                'remarks' => $att->remarks,
                'device_uuid' => $att->device_uuid,
                'ip_address' => $att->ip_address,
            ];
        });

        // Granular telemetry intervals (attendance_activities)
        $attendanceIds = $records->pluck('id')->all();
        $activitiesQuery = AttendanceActivity::whereIn('attendance_id', $attendanceIds)
            ->orderByDesc('interval_start');

        if ($request->filled('activity_date')) {
            $filterDate = Carbon::parse((string) $request->activity_date)->toDateString();
            $dateAtt = $records->firstWhere('date', $filterDate);
            if ($dateAtt) {
                $activitiesQuery->where('attendance_id', $dateAtt->id);
            }
        }

        $telemetryIntervals = $activitiesQuery->take(250)->get()->map(function (AttendanceActivity $act) {
            return [
                'id' => $act->id,
                'attendance_id' => $act->attendance_id,
                'interval_start' => $act->interval_start?->toIso8601String(),
                'interval_end' => $act->interval_end?->toIso8601String(),
                'start_time' => $act->interval_start ? Carbon::parse($act->interval_start)->format('H:i:s') : null,
                'end_time' => $act->interval_end ? Carbon::parse($act->interval_end)->format('H:i:s') : null,
                'state' => $act->state,
                'active_seconds' => $act->active_seconds,
                'idle_seconds' => $act->idle_seconds,
                'break_seconds' => $act->break_seconds,
                'break_type' => $act->break_type,
                'source' => $act->source,
            ];
        });

        // Workstation sessions
        $workstations = [];
        if ($userId) {
            $workstations = DeviceSession::with('device')
                ->where('user_id', $userId)
                ->orderByDesc('last_activity_at')
                ->take(8)
                ->get()
                ->map(function (DeviceSession $ds) {
                    return [
                        'id' => $ds->id,
                        'device_name' => $ds->device?->device_name ?? 'Desktop Workstation',
                        'platform' => $ds->device?->platform ?? 'Windows',
                        'os_version' => $ds->device?->os_version,
                        'ip_address' => $ds->ip_address,
                        'session_status' => $ds->session_status,
                        'started_at' => $ds->started_at?->toIso8601String(),
                        'last_activity_at' => $ds->last_activity_at?->toIso8601String(),
                    ];
                })
                ->all();
        }

        return [
            'period' => [
                'key' => $period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => $employee->full_name,
                'official_email' => $employee->official_email,
                'personal_email' => $employee->personal_email,
                'phone_number' => $employee->phone_number,
                'avatar_url' => $employee->avatar_url,
                'gender' => $employee->gender,
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'date_of_joining' => $employee->date_of_joining?->toDateString(),
                'employment_type' => $employee->employment_type,
                'employment_status' => $employee->employment_status,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'name' => $employee->department->name,
                    'code' => $employee->department->code,
                ] : null,
                'designation' => $employee->designation ? [
                    'id' => $employee->designation->id,
                    'title' => $employee->designation->name,
                    'name' => $employee->designation->name,
                    'code' => $employee->designation->code,
                ] : null,
                'reporting_manager' => $employee->reportingManager ? [
                    'id' => $employee->reportingManager->id,
                    'name' => $employee->reportingManager->full_name,
                    'code' => $employee->reportingManager->employee_code,
                ] : null,
                'user' => $employee->user ? [
                    'id' => $employee->user->id,
                    'name' => $employee->user->name,
                    'email' => $employee->user->email,
                    'status' => $employee->user->status,
                    'roles' => $employee->user->roles->pluck('name')->all(),
                ] : null,
            ],
            'live_presence' => [
                'state' => $liveState,
                'is_clocked_in' => $todayRecord && $todayRecord->clock_out_at === null,
                'clock_in_at' => $todayRecord?->clock_in_at?->toIso8601String(),
                'clock_out_at' => $todayRecord?->clock_out_at?->toIso8601String(),
                'today_active_minutes' => $todayRecord?->active_minutes ?? 0,
                'today_idle_minutes' => $todayRecord?->idle_minutes ?? 0,
                'today_break_minutes' => $todayRecord?->break_minutes ?? 0,
                'modality' => $todayRecord?->modality,
                'status' => $todayRecord?->status,
            ],
            'statistics' => [
                'has_data' => $totalDays > 0,
                'total_days' => $totalDays,
                'total_duration_hours' => round($totalDurationMinutes / 60, 1),
                'total_active_hours' => round($totalActiveMinutes / 60, 1),
                'total_idle_hours' => round($totalIdleMinutes / 60, 1),
                'total_break_hours' => round($totalBreakMinutes / 60, 1),
                'avg_daily_active_hours' => $totalDays > 0 ? round(($totalActiveMinutes / 60) / $totalDays, 1) : 0,
                'avg_daily_duration_hours' => $totalDays > 0 ? round(($totalDurationMinutes / 60) / $totalDays, 1) : 0,
                'productivity_percentage' => $totalDays > 0 ? $averageProductivity : null,
                'idle_ratio_percentage' => $totalWorkMinutes > 0 ? round(($totalIdleMinutes / $totalWorkMinutes) * 100, 1) : null,
                'punctuality_rate' => $totalDays > 0 ? $punctualityRate : null,
                'status_distribution' => [
                    'present' => $presentCount,
                    'late' => $lateCount,
                    'half_day' => $halfDayCount,
                    'absent' => $absentCount,
                    'on_leave' => $onLeaveCount,
                ],
                'modality_distribution' => [
                    'office' => $officeCount,
                    'wfh' => $wfhCount,
                    'hybrid' => $hybridCount,
                ],
            ],
            'daily_timeline' => $timeline,
            'raw_attendances' => $records->values()->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'formatted_date' => Carbon::parse($a->date)->format('M d, Y'),
                'clock_in_at' => $a->clock_in_at?->toIso8601String(),
                'clock_out_at' => $a->clock_out_at?->toIso8601String(),
                'clock_in_time' => $a->clock_in_at ? Carbon::parse($a->clock_in_at)->format('H:i') : '—',
                'clock_out_time' => $a->clock_out_at ? Carbon::parse($a->clock_out_at)->format('H:i') : '—',
                'duration_minutes' => $a->total_duration_minutes,
                'duration_hours' => round($a->total_duration_minutes / 60, 2),
                'active_minutes' => $a->active_minutes,
                'active_hours' => round($a->active_minutes / 60, 2),
                'idle_minutes' => $a->idle_minutes,
                'idle_hours' => round($a->idle_minutes / 60, 2),
                'break_minutes' => $a->break_minutes,
                'break_hours' => round($a->break_minutes / 60, 2),
                'productivity_score' => (float) $a->productivity_score,
                'status' => $a->status,
                'modality' => $a->modality,
                'is_manual_override' => (bool) $a->is_manual_override,
                'override_by' => $a->overrideBy ? ['id' => $a->overrideBy->id, 'name' => $a->overrideBy->name] : null,
                'remarks' => $a->remarks,
                'device_uuid' => $a->device_uuid,
                'ip_address' => $a->ip_address,
            ]),
            'telemetry_intervals' => $telemetryIntervals,
            'workstations' => $workstations,
        ];
    }
}
