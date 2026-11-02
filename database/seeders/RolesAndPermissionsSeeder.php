<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions grouped by module
        $modules = [
            'Employee' => [
                'employees.view',
                'employees.create',
                'employees.update',
                'employees.archive',
                'employees.export',
            ],
            'Department' => [
                'departments.view',
                'departments.create',
                'departments.update',
                'departments.manage',
            ],
            'Attendance' => [
                'attendance.view',
                'attendance.mark',
                'attendance.update',
                'attendance.approve',
                'attendance.export',
            ],
            'Leave' => [
                'leaves.view',
                'leaves.apply',
                'leaves.approve',
                'leaves.reject',
                'leaves.manage',
            ],
            'Holiday' => [
                'holidays.view',
                'holidays.create',
                'holidays.update',
                'holidays.delete',
                'holidays.manage',
            ],
            'Payroll' => [
                'payroll.view',
                'payroll.create',
                'payroll.process',
                'payroll.approve',
                'payroll.export',
            ],
            'Payslip' => [
                'payslips.view',
                'payslips.generate',
                'payslips.download',
            ],
            'Invoice' => [
                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.approve',
                'invoices.export',
                'invoices.void',
            ],
            'Expense' => [
                'expenses.view',
                'expenses.create',
                'expenses.update',
                'expenses.approve',
                'expenses.reject',
                'expenses.export',
            ],
            'UserManagement' => [
                'users.view',
                'users.create',
                'users.update',
                'users.deactivate',
            ],
            'Roles' => [
                'roles.view',
                'roles.create',
                'roles.update',
                'roles.delete',
                'roles.assign',
            ],
            'Permissions' => [
                'permissions.view',
                'permissions.manage',
            ],
            'Settings' => [
                'settings.view',
                'settings.update',
            ],
            'Audit' => [
                'audit.view',
                'audit.export',
            ],
            'Devices' => [
                'devices.view',
                'devices.manage',
                'devices.revoke',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
            ],
        ];

        $allPermissionNames = [];
        foreach ($modules as $group => $permissions) {
            foreach ($permissions as $permName) {
                Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => 'web'],
                    ['module_group' => $group]
                );
                $allPermissionNames[] = $permName;
            }
        }

        // Define Roles
        $roles = [
            'Super Admin' => [
                'description' => 'System administrator with complete oversight',
                'is_system_role' => true,
                'permissions' => $allPermissionNames,
            ],
            'Admin' => [
                'description' => 'Operations administrator',
                'is_system_role' => true,
                'permissions' => array_diff($allPermissionNames, [
                    'roles.delete',
                    'permissions.manage',
                ]),
            ],
            'HR Manager' => [
                'description' => 'Human Resources Department Head',
                'is_system_role' => true,
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.update', 'employees.archive', 'employees.export',
                    'departments.view',
                    'attendance.view', 'attendance.mark', 'attendance.update', 'attendance.approve', 'attendance.export',
                    'leaves.view', 'leaves.apply', 'leaves.approve', 'leaves.reject', 'leaves.manage',
                    'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete', 'holidays.manage',
                    'payroll.view', 'payroll.create', 'payroll.process', 'payroll.export',
                    'payslips.view', 'payslips.generate', 'payslips.download',
                    'expenses.view', 'expenses.create',
                    'users.view',
                    'reports.view', 'reports.export',
                ],
            ],
            'HR Executive' => [
                'description' => 'Human Resources Executive',
                'is_system_role' => false,
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.update',
                    'departments.view',
                    'attendance.view', 'attendance.mark',
                    'leaves.view', 'leaves.apply',
                    'holidays.view',
                    'payslips.view', 'payslips.download',
                    'expenses.view', 'expenses.create',
                ],
            ],
            'Finance Manager' => [
                'description' => 'Finance & Accounting Head',
                'is_system_role' => true,
                'permissions' => [
                    'employees.view',
                    'payroll.view', 'payroll.create', 'payroll.process', 'payroll.approve', 'payroll.export',
                    'payslips.view', 'payslips.generate', 'payslips.download',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.approve', 'invoices.export', 'invoices.void',
                    'expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.reject', 'expenses.export',
                    'reports.view', 'reports.export',
                ],
            ],
            'Accountant' => [
                'description' => 'Billing and accounts staff',
                'is_system_role' => false,
                'permissions' => [
                    'employees.view',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.export',
                    'expenses.view', 'expenses.create', 'expenses.export',
                    'payslips.view', 'payslips.download',
                ],
            ],
            'Department Manager' => [
                'description' => 'Team lead or department head',
                'is_system_role' => false,
                'permissions' => [
                    'employees.view',
                    'attendance.view', 'attendance.mark', 'attendance.approve',
                    'leaves.view', 'leaves.apply', 'leaves.approve', 'leaves.reject',
                    'holidays.view',
                    'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.reject',
                    'payslips.view', 'payslips.download',
                    'reports.view',
                ],
            ],
            'Employee' => [
                'description' => 'Standard employee self-service user',
                'is_system_role' => false,
                'permissions' => [
                    'attendance.mark', 'attendance.view',
                    'leaves.apply', 'leaves.view',
                    'holidays.view',
                    'payslips.view', 'payslips.download',
                    'expenses.create', 'expenses.view',
                ],
            ],
            'Auditor' => [
                'description' => 'Read-only compliance auditor',
                'is_system_role' => false,
                'permissions' => [
                    'employees.view', 'employees.export',
                    'departments.view',
                    'attendance.view', 'attendance.export',
                    'leaves.view',
                    'holidays.view',
                    'payroll.view', 'payroll.export',
                    'invoices.view', 'invoices.export',
                    'expenses.view', 'expenses.export',
                    'audit.view', 'audit.export',
                    'reports.view', 'reports.export',
                ],
            ],
        ];

        foreach ($roles as $roleName => $data) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'description' => $data['description'],
                    'is_system_role' => $data['is_system_role'],
                ]
            );

            $role->syncPermissions($data['permissions']);
        }
    }
}
