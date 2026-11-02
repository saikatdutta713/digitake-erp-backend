<?php

namespace App\Modules\Employee\Services;

use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeStatusHistory;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new employee profile and associated user identity.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor) {
            // Generate sequential employee code EMP-XXXX
            $lastEmployee = Employee::withTrashed()->orderByDesc('id')->first();
            $nextSeq = $lastEmployee ? ($lastEmployee->id + 1) : 1;
            $employeeCode = sprintf('EMP-%04d', $nextSeq);

            // Create or resolve associated user account
            $password = ! empty($data['password']) ? $data['password'] : Str::random(12);
            $user = User::create([
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['official_email'],
                'password' => Hash::make($password),
                'status' => 'active',
            ]);

            // Assign standard Employee role
            $user->assignRole('Employee');

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_code' => $employeeCode,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'official_email' => $data['official_email'],
                'personal_email' => $data['personal_email'] ?? null,
                'phone_number' => $data['phone_number'],
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'date_of_joining' => $data['date_of_joining'],
                'department_id' => $data['department_id'],
                'designation_id' => $data['designation_id'],
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'employment_status' => $data['employment_status'] ?? 'active',
                'current_address' => $data['current_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
            ]);

            // Record initial status history
            EmployeeStatusHistory::create([
                'employee_id' => $employee->id,
                'changed_by' => $actor->id,
                'old_status' => 'new',
                'new_status' => $employee->employment_status,
                'effective_date' => $employee->date_of_joining,
                'reason' => 'Initial employee onboarding',
                'created_at' => now(),
            ]);

            $this->auditService->log('employee.created', $employee, null, [
                'employee_code' => $employee->employee_code,
                'official_email' => $employee->official_email,
                'department_id' => $employee->department_id,
            ], $actor);

            return $employee;
        });
    }

    /**
     * Update employee profile information.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, User $actor): Employee
    {
        $old = $employee->only([
            'first_name', 'last_name', 'phone_number', 'department_id', 'designation_id',
        ]);

        $employee->update($data);

        // Keep User display name in sync if name changed
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $employee->user?->update([
                'name' => "{$employee->first_name} {$employee->last_name}",
            ]);
        }

        $this->auditService->log(
            'employee.updated',
            $employee,
            $old,
            $employee->only(array_keys($old)),
            $actor
        );

        return $employee;
    }

    /**
     * Update employment status (active -> resigned, terminated, archived).
     */
    public function updateStatus(
        Employee $employee,
        string $newStatus,
        string $effectiveDate,
        string $reason,
        User $actor
    ): void {
        DB::transaction(function () use ($employee, $newStatus, $effectiveDate, $reason, $actor) {
            $oldStatus = $employee->employment_status;

            EmployeeStatusHistory::create([
                'employee_id' => $employee->id,
                'changed_by' => $actor->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $employee->update(['employment_status' => $newStatus]);

            // If employee is leaving, disable system user login and revoke tokens
            if (in_array($newStatus, ['resigned', 'terminated', 'archived', 'inactive'], true)) {
                if ($employee->user) {
                    $employee->user->update(['status' => 'inactive']);
                    $employee->user->tokens()->delete();
                }
            }

            $this->auditService->log(
                'employee.status_changed',
                $employee,
                ['employment_status' => $oldStatus],
                ['employment_status' => $newStatus, 'reason' => $reason],
                $actor
            );
        });
    }

    /**
     * Manage employee confidential bank details.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBankDetails(Employee $employee, array $data, User $actor): EmployeeBankDetail
    {
        $bankDetail = EmployeeBankDetail::updateOrCreate(
            ['employee_id' => $employee->id],
            $data
        );

        $this->auditService->log('employee.bank_updated', $employee, null, [
            'bank_name' => $data['bank_name'],
            'account_type' => $data['account_type'],
        ], $actor);

        return $bankDetail;
    }
}
