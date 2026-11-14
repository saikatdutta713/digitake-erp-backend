<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Retrieve aggregated, dynamic dashboard metrics, department attendance rates,
     * recent leave applications, system notices, and top performers.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = ucfirst(strtolower((string) $request->query('period', 'Daily')));
        if (! in_array($period, ['Daily', 'Weekly', 'Monthly', 'Yearly'], true)) {
            $period = 'Daily';
        }

        $today = Carbon::today();
        $todayStr = $today->toDateString();

        // Total active personnel
        $totalHeadcount = Employee::where('employment_status', 'active')->count();
        if ($totalHeadcount === 0) {
            $totalHeadcount = User::where('status', 'active')->count();
        }

        // 30-day employee growth calculation
        $joinedLast30Days = Employee::where('employment_status', 'active')
            ->where('date_of_joining', '>=', $today->copy()->subDays(30)->toDateString())
            ->count();
        $headcount30DaysAgo = max(1, $totalHeadcount - $joinedLast30Days);
        $growthPercent = $headcount30DaysAgo > 0
            ? round((($totalHeadcount - $headcount30DaysAgo) / $headcount30DaysAgo) * 100)
            : 0;

        // Today Attendance Metrics
        $todayAttendances = Attendance::where('date', $todayStr)->get();
        $todayPresents = $todayAttendances->whereIn('status', ['present', 'late', 'half_day'])->count();

        // Today Leaves (approved leaves covering today)
        $todayLeaves = Leave::where('status', 'approved')
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->count();

        $todayAbsents = max(0, $totalHeadcount - $todayPresents - $todayLeaves);

        $todayPresentPct = $totalHeadcount > 0 ? round(($todayPresents / $totalHeadcount) * 100) : 0;
        $todayAbsentPct = $totalHeadcount > 0 ? round(($todayAbsents / $totalHeadcount) * 100) : 0;
        $todayLeavePct = $totalHeadcount > 0 ? round(($todayLeaves / $totalHeadcount) * 100) : 0;

        // Period Date Range for Attendance Statistics
        switch ($period) {
            case 'Weekly':
                $startDate = $today->copy()->subDays(6)->startOfDay();
                $endDate = $today->copy()->endOfDay();
                $periodDays = 7;
                break;
            case 'Monthly':
                $startDate = $today->copy()->startOfMonth()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                $periodDays = max(1, $today->day);
                break;
            case 'Yearly':
                $startDate = $today->copy()->startOfYear()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                $periodDays = max(1, $today->dayOfYear);
                break;
            case 'Daily':
            default:
                $startDate = $today->copy()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                $periodDays = 1;
                break;
        }

        // Query attendances in period
        $periodAttendances = Attendance::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])->get();
        $periodPresents = $periodAttendances->whereIn('status', ['present', 'late', 'half_day'])->count();

        $periodLeaves = Leave::where('status', 'approved')
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->count();

        $expectedTotalPunches = max(1, $totalHeadcount * $periodDays);
        $periodPresentRate = min(100, round(($periodPresents / $expectedTotalPunches) * 100));
        $periodLeaveRate = min(100, round(($periodLeaves / $expectedTotalPunches) * 100));
        $periodAbsentRate = max(0, 100 - $periodPresentRate - $periodLeaveRate);

        // Department-wise Attendance breakdown
        $departments = Department::withCount(['employees' => function ($q) {
            $q->where('employment_status', 'active');
        }])->orderBy('name')->get();

        $departmentBars = $departments->map(function (Department $dept) use ($startDate, $endDate, $periodDays) {
            $employeeIds = Employee::where('department_id', $dept->id)
                ->where('employment_status', 'active')
                ->pluck('id')
                ->all();

            $staffCount = count($employeeIds);
            if ($staffCount === 0) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code ?? strtoupper(substr($dept->name, 0, 4)),
                    'staff_count' => 0,
                    'present_count' => 0,
                    'attendance_percent' => 0,
                ];
            }

            $presentCount = Attendance::whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereIn('status', ['present', 'late', 'half_day'])
                ->count();

            $expected = $staffCount * $periodDays;
            $percent = $expected > 0 ? min(100, round(($presentCount / $expected) * 100)) : 0;

            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code ?? strtoupper(substr($dept->name, 0, 4)),
                'staff_count' => $staffCount,
                'present_count' => $presentCount,
                'attendance_percent' => $percent,
            ];
        })->values()->all();

        // Recent Leave Applications
        $recentLeaves = Leave::with(['user', 'employee.department'])
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function (Leave $l) {
                $employeeName = $l->employee?->full_name
                    ?? $l->user?->name
                    ?? 'Employee #'.$l->user_id;

                $avatarUrl = $l->employee?->avatar_url
                    ?? $l->user?->avatar_url
                    ?? 'https://api.dicebear.com/9.x/lorelei/svg?seed='.urlencode($employeeName);

                return [
                    'id' => $l->id,
                    'applicant_name' => $employeeName,
                    'avatar_url' => $avatarUrl,
                    'department' => $l->employee?->department?->name ?? 'General Operations',
                    'leave_type' => ucfirst($l->leave_type ?? 'Personal'),
                    'reason' => $l->reason ?? 'Personal Leave',
                    'status' => ucfirst($l->status ?? 'pending'),
                    'days' => $l->days ?? 1,
                    'start_date' => $l->start_date?->format('d-M-Y'),
                    'end_date' => $l->end_date?->format('d-M-Y'),
                ];
            })->all();

        // Operational Notices & Announcements
        $milestones = ProjectMilestone::with('project')
            ->latest('id')
            ->take(3)
            ->get();

        $notices = [];
        if ($milestones->isNotEmpty()) {
            foreach ($milestones as $m) {
                $notices[] = [
                    'id' => 'm-'.$m->id,
                    'title' => ($m->project ? $m->project->name.': ' : '').$m->title,
                    'tag' => 'Project Milestone',
                    'date' => $m->due_date ? Carbon::parse($m->due_date)->format('d-M-Y') : $today->format('d-M-Y'),
                    'starred' => $m->status === 'completed',
                ];
            }
        }

        // Add company operational announcement
        $notices[] = [
            'id' => 'n-corp-1',
            'title' => 'Weekly Executive & Engineering Sprint Synchronization',
            'tag' => 'Corporate Meeting',
            'date' => $today->format('d-M-Y'),
            'starred' => true,
        ];
        $notices[] = [
            'id' => 'n-corp-2',
            'title' => 'Quarterly Operational Compliance & Policy Review',
            'tag' => 'Management Audit',
            'date' => $today->copy()->addDays(5)->format('d-M-Y'),
            'starred' => false,
        ];

        // Employee Awards & Top Performers
        $awardList = [
            [
                'title' => 'Gascapitol Innovator',
                'category' => 'Technical Excellence',
                'bonus' => '$1,500',
            ],
            [
                'title' => 'Zero Downtime Award',
                'category' => 'Cloud & Infrastructure',
                'bonus' => '$1,200',
            ],
            [
                'title' => 'Financial Governance Excellence',
                'category' => 'Financial Integrity',
                'bonus' => '$1,500',
            ],
            [
                'title' => 'Enterprise Sales Star',
                'category' => 'Business Growth',
                'bonus' => '$2,000',
            ],
            [
                'title' => 'People & Operations Star',
                'category' => 'People Leadership',
                'bonus' => '$1,800',
            ],
        ];

        $employees = Employee::with(['department', 'designation'])
            ->where('employment_status', 'active')
            ->orderBy('id')
            ->take(5)
            ->get();

        $topPerformers = [];
        foreach ($employees as $index => $emp) {
            $awardMeta = $awardList[$index % count($awardList)];
            $topPerformers[] = [
                'id' => $emp->id,
                'sl' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'name' => $emp->full_name,
                'avatar_url' => $emp->avatar_url ?? 'https://api.dicebear.com/9.x/lorelei/svg?seed='.urlencode($emp->full_name),
                'department' => $emp->department?->name ?? 'Operations',
                'award_title' => $awardMeta['title'],
                'category' => $awardMeta['category'],
                'bonus' => $awardMeta['bonus'],
                'date' => $emp->date_of_joining ? Carbon::parse($emp->date_of_joining)->format('d-m-y') : $today->format('d-m-y'),
            ];
        }

        return ApiResponse::success([
            'kpis' => [
                'total_employees' => $totalHeadcount,
                'employees_30d_ago' => $headcount30DaysAgo,
                'growth_percent' => $growthPercent,
                'today_presents' => $todayPresents,
                'today_present_percent' => $todayPresentPct,
                'today_absents' => $todayAbsents,
                'today_absent_percent' => $todayAbsentPct,
                'today_leaves' => $todayLeaves,
                'today_leave_percent' => $todayLeavePct,
            ],
            'attendance_statistic' => [
                'period' => $period,
                'label' => "{$period} Attendance Statistic",
                'present_percent' => $periodPresentRate,
                'absent_percent' => $periodAbsentRate,
                'leave_percent' => $periodLeaveRate,
                'departments' => $departmentBars,
            ],
            'recent_leaves' => $recentLeaves,
            'notices' => array_slice($notices, 0, 4),
            'awards' => $topPerformers,
        ], 'Dashboard statistics retrieved successfully.');
    }
}
