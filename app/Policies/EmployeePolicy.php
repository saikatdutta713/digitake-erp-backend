<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any employees.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('employees.view');
    }

    /**
     * Determine whether the user can view the specific employee.
     */
    public function view(User $actor, Employee $employee): bool
    {
        // Own profile access
        if ($actor->employee && $actor->employee->id === $employee->id) {
            return true;
        }

        // Must have permission
        if (! $actor->hasPermissionTo('employees.view')) {
            return false;
        }

        // Company-wide HR/Finance/Admin roles
        if ($actor->hasAnyRole(['Super Admin', 'Admin', 'HR Manager', 'HR Executive', 'Auditor'])) {
            return true;
        }

        // Department Manager scope
        if ($actor->hasRole('Department Manager')) {
            $managedDept = $actor->employee?->managedDepartment;
            if ($managedDept && $managedDept->id === $employee->department_id) {
                return true;
            }

            if ($actor->employee && $actor->employee->department_id === $employee->department_id) {
                return true;
            }

            return false;
        }

        // Other roles with employees.view (e.g. Accountant) see basic public directory
        return true;
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('employees.create');
    }

    /**
     * Determine whether the user can update the employee.
     */
    public function update(User $actor, Employee $employee): bool
    {
        if ($actor->hasPermissionTo('employees.update')) {
            return true;
        }

        // Allow updating own basic profile
        return $actor->employee && $actor->employee->id === $employee->id;
    }

    /**
     * Determine whether the user can archive or change employment status.
     */
    public function archive(User $actor, Employee $employee): bool
    {
        // Cannot archive self
        if ($actor->employee && $actor->employee->id === $employee->id) {
            return false;
        }

        return $actor->hasPermissionTo('employees.archive');
    }

    /**
     * Determine whether the user can view confidential bank details.
     */
    public function viewBank(User $actor, Employee $employee): bool
    {
        // Employee viewing their own bank details
        if ($actor->employee && $actor->employee->id === $employee->id) {
            return true;
        }

        // Restricted strictly to authorized administrative roles
        return $actor->hasAnyRole(['Super Admin', 'HR Manager', 'Finance Manager']);
    }

    /**
     * Determine whether the user can update bank details.
     */
    public function updateBank(User $actor, Employee $employee): bool
    {
        return $actor->hasAnyRole(['Super Admin', 'HR Manager', 'Finance Manager']);
    }
}
