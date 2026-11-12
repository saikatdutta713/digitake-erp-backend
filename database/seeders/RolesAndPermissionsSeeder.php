<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
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

        // 1. Look up departments if they exist
        $eng = Department::where('code', 'ENG')->first();
        $hr = Department::where('code', 'HR')->first();
        $fin = Department::where('code', 'FIN')->first();
        $sales = Department::where('code', 'SALES')->first();

        // 2. Clean up designations that were mistakenly placed into roles table
        Role::whereIn('name', [
            'Engineering Manager', 'Tech Lead', 'Senior Software Engineer',
            'Software Engineer', 'DevOps & Cloud Engineer', 'QA Automation Engineer',
            'Talent Acquisition Specialist', 'Senior Accountant', 'Financial Analyst',
            'Business Development Manager', 'Account Executive', 'Digital Marketing Specialist',
        ])->delete();

        // 3. Define permissions grouped by module
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
            'Projects' => [
                'projects.view',
                'projects.create',
                'projects.update',
                'projects.manage',
            ],
            'Recruitment' => [
                'recruitment.view',
                'recruitment.create',
                'recruitment.update',
                'recruitment.manage',
            ],
            'Loans' => [
                'loans.view',
                'loans.create',
                'loans.update',
                'loans.manage',
            ],
            'Awards' => [
                'awards.view',
                'awards.create',
                'awards.update',
                'awards.manage',
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

        // 4. Define Standard Roles and Single Departmental Role per Department
        $roles = [
            'Super Admin' => [
                'description' => 'System administrator with complete oversight',
                'is_system_role' => true,
                'role_type' => 'system',
                'department_id' => null,
                'permissions' => $allPermissionNames,
            ],
            'Admin' => [
                'description' => 'Operations administrator with elevated management privileges',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
                'permissions' => array_diff($allPermissionNames, [
                    'roles.delete',
                    'permissions.manage',
                ]),
            ],
            'HR Manager' => [
                'description' => 'Human Resources Department Head',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.update', 'employees.archive', 'employees.export',
                    'departments.view', 'departments.update',
                    'attendance.view', 'attendance.mark', 'attendance.update', 'attendance.approve', 'attendance.export',
                    'leaves.view', 'leaves.apply', 'leaves.approve', 'leaves.reject', 'leaves.manage',
                    'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete', 'holidays.manage',
                    'payroll.view', 'payroll.create', 'payroll.process', 'payroll.export',
                    'payslips.view', 'payslips.generate', 'payslips.download',
                    'expenses.view', 'expenses.create', 'expenses.approve',
                    'users.view',
                    'reports.view', 'reports.export',
                    'recruitment.view', 'recruitment.create', 'recruitment.update', 'recruitment.manage',
                    'awards.view', 'awards.create', 'awards.update', 'awards.manage',
                ],
            ],
            'HR Executive' => [
                'description' => 'Human Resources Executive',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
                'permissions' => [
                    'employees.view', 'employees.create', 'employees.update',
                    'departments.view',
                    'attendance.view', 'attendance.mark',
                    'leaves.view', 'leaves.apply',
                    'holidays.view',
                    'payslips.view', 'payslips.download',
                    'expenses.view', 'expenses.create',
                    'recruitment.view', 'recruitment.update',
                    'awards.view',
                ],
            ],
            'Finance Manager' => [
                'description' => 'Finance & Accounting Head',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
                'permissions' => [
                    'employees.view',
                    'payroll.view', 'payroll.create', 'payroll.process', 'payroll.approve', 'payroll.export',
                    'payslips.view', 'payslips.generate', 'payslips.download',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.approve', 'invoices.export', 'invoices.void',
                    'expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.reject', 'expenses.export',
                    'loans.view', 'loans.create', 'loans.update', 'loans.manage',
                    'reports.view', 'reports.export',
                    'attendance.mark', 'attendance.view',
                    'leaves.apply', 'leaves.view',
                    'holidays.view',
                ],
            ],
            'Accountant' => [
                'description' => 'Billing and accounts staff',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
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
                'role_type' => 'custom',
                'department_id' => null,
                'permissions' => [
                    'employees.view',
                    'attendance.view', 'attendance.mark', 'attendance.update', 'attendance.approve',
                    'leaves.view', 'leaves.apply', 'leaves.approve', 'leaves.reject',
                    'holidays.view',
                    'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.reject',
                    'projects.view', 'projects.create', 'projects.update',
                    'recruitment.view',
                    'payslips.view', 'payslips.download',
                    'reports.view',
                ],
            ],
            'Employee' => [
                'description' => 'Standard employee self-service user',
                'is_system_role' => false,
                'role_type' => 'custom',
                'department_id' => null,
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
                'role_type' => 'custom',
                'department_id' => null,
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

            // -----------------------------------------------------------------
            // Department Roles (Each department is a role, as before)
            // -----------------------------------------------------------------
            'Engineering' => [
                'description' => 'Departmental role for Engineering',
                'is_system_role' => false,
                'role_type' => 'department',
                'department_id' => $eng?->id,
                'permissions' => [
                    'departments.view',
                    'attendance.mark', 'attendance.view',
                    'leaves.apply', 'leaves.view',
                    'holidays.view',
                    'devices.view',
                    'expenses.create', 'expenses.view',
                    'payslips.view', 'payslips.download',
                ],
            ],
            'Human Resources' => [
                'description' => 'Departmental role for Human Resources',
                'is_system_role' => false,
                'role_type' => 'department',
                'department_id' => $hr?->id,
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
            'Finance & Accounts' => [
                'description' => 'Departmental role for Finance & Accounts',
                'is_system_role' => false,
                'role_type' => 'department',
                'department_id' => $fin?->id,
                'permissions' => [
                    'employees.view',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.export',
                    'expenses.view', 'expenses.create', 'expenses.export',
                    'payslips.view', 'payslips.download',
                    'attendance.mark', 'attendance.view',
                    'leaves.apply', 'leaves.view',
                    'holidays.view',
                ],
            ],
            'Sales & Marketing' => [
                'description' => 'Departmental role for Sales & Marketing',
                'is_system_role' => false,
                'role_type' => 'department',
                'department_id' => $sales?->id,
                'permissions' => [
                    'departments.view',
                    'invoices.view',
                    'expenses.create', 'expenses.view',
                    'reports.view',
                    'attendance.mark', 'attendance.view',
                    'leaves.apply', 'leaves.view',
                    'holidays.view',
                    'payslips.view', 'payslips.download',
                ],
            ],
        ];

        foreach ($roles as $roleName => $data) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'description' => $data['description'],
                    'is_system_role' => $data['is_system_role'],
                    'role_type' => $data['role_type'],
                    'department_id' => $data['department_id'],
                ]
            );

            $role->update([
                'is_system_role' => $data['is_system_role'],
                'role_type' => $data['role_type'],
                'department_id' => $data['department_id'],
                'description' => $data['description'],
            ]);

            $role->syncPermissions($data['permissions']);
        }
    }
}
