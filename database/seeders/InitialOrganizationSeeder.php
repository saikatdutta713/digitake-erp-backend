<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeStatusHistory;
use App\Models\Leave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Departments
        $eng = Department::firstOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Engineering', 'is_active' => true]
        );

        $hr = Department::firstOrCreate(
            ['code' => 'HR'],
            ['name' => 'Human Resources', 'is_active' => true]
        );

        $fin = Department::firstOrCreate(
            ['code' => 'FIN'],
            ['name' => 'Finance & Accounts', 'is_active' => true]
        );

        $sales = Department::firstOrCreate(
            ['code' => 'SALES'],
            ['name' => 'Sales & Marketing', 'is_active' => true]
        );

        // Sync departmental roles (each department is a role as before)
        foreach ([$eng, $hr, $fin, $sales] as $dept) {
            $deptRole = Role::firstOrCreate(
                ['name' => $dept->name, 'guard_name' => 'web'],
                [
                    'description' => "Departmental role for {$dept->name}",
                    'is_system_role' => false,
                    'role_type' => 'department',
                    'department_id' => $dept->id,
                ]
            );
            $deptRole->update([
                'role_type' => 'department',
                'department_id' => $dept->id,
                'description' => "Departmental role for {$dept->name}",
            ]);
        }

        // 2. IT Industry Designations
        // Engineering
        $desigEM = Designation::firstOrCreate(
            ['code' => 'EM'],
            ['name' => 'Engineering Manager', 'department_id' => $eng->id, 'level' => 5, 'is_active' => true]
        );
        $desigTL = Designation::firstOrCreate(
            ['code' => 'TL'],
            ['name' => 'Tech Lead', 'department_id' => $eng->id, 'level' => 4, 'is_active' => true]
        );
        $desigSSE = Designation::firstOrCreate(
            ['code' => 'SSE'],
            ['name' => 'Senior Software Engineer', 'department_id' => $eng->id, 'level' => 3, 'is_active' => true]
        );
        $desigSE = Designation::firstOrCreate(
            ['code' => 'SE'],
            ['name' => 'Software Engineer', 'department_id' => $eng->id, 'level' => 2, 'is_active' => true]
        );
        $desigDevOps = Designation::firstOrCreate(
            ['code' => 'DEVOPS'],
            ['name' => 'DevOps & Cloud Engineer', 'department_id' => $eng->id, 'level' => 3, 'is_active' => true]
        );
        $desigQA = Designation::firstOrCreate(
            ['code' => 'QA'],
            ['name' => 'QA Automation Engineer', 'department_id' => $eng->id, 'level' => 2, 'is_active' => true]
        );

        // Human Resources
        $desigHRM = Designation::firstOrCreate(
            ['code' => 'HRM'],
            ['name' => 'HR Manager', 'department_id' => $hr->id, 'level' => 4, 'is_active' => true]
        );
        $desigTAS = Designation::firstOrCreate(
            ['code' => 'TAS'],
            ['name' => 'Talent Acquisition Specialist', 'department_id' => $hr->id, 'level' => 3, 'is_active' => true]
        );
        $desigHRE = Designation::firstOrCreate(
            ['code' => 'HRE'],
            ['name' => 'HR Executive', 'department_id' => $hr->id, 'level' => 2, 'is_active' => true]
        );

        // Finance & Accounts
        $desigFM = Designation::firstOrCreate(
            ['code' => 'FM'],
            ['name' => 'Finance Manager', 'department_id' => $fin->id, 'level' => 4, 'is_active' => true]
        );
        $desigSA = Designation::firstOrCreate(
            ['code' => 'SA'],
            ['name' => 'Senior Accountant', 'department_id' => $fin->id, 'level' => 3, 'is_active' => true]
        );
        $desigFA = Designation::firstOrCreate(
            ['code' => 'FA'],
            ['name' => 'Financial Analyst', 'department_id' => $fin->id, 'level' => 2, 'is_active' => true]
        );

        // Sales & Marketing
        $desigBDM = Designation::firstOrCreate(
            ['code' => 'BDM'],
            ['name' => 'Business Development Manager', 'department_id' => $sales->id, 'level' => 4, 'is_active' => true]
        );
        $desigAE = Designation::firstOrCreate(
            ['code' => 'AE'],
            ['name' => 'Account Executive', 'department_id' => $sales->id, 'level' => 3, 'is_active' => true]
        );
        $desigDMS = Designation::firstOrCreate(
            ['code' => 'DMS'],
            ['name' => 'Digital Marketing Specialist', 'department_id' => $sales->id, 'level' => 2, 'is_active' => true]
        );

        // 3. Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@digitake.local'],
            [
                'name' => 'Digitake Super Admin',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SuperAdmin',
                'password' => Hash::make('Admin@12345'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->update(['avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SuperAdmin']);
        $superAdmin->syncRoles(['Super Admin']);

        // 4. HR Manager (Sarah Connor) - Human Resources
        $hrUser = User::firstOrCreate(
            ['email' => 'hr@digitake.local'],
            [
                'name' => 'Sarah Connor',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SarahConnor',
                'password' => Hash::make('Password@123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $hrUser->update([
            'name' => 'Sarah Connor',
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SarahConnor',
        ]);
        $hrUser->syncRoles(['HR Manager', 'Human Resources']);

        $hrEmployee = Employee::firstOrCreate(
            ['official_email' => 'hr@digitake.local'],
            [
                'user_id' => $hrUser->id,
                'employee_code' => '7C9E6679',
                'first_name' => 'Sarah',
                'last_name' => 'Connor',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SarahConnor',
                'phone_number' => '+1555001001',
                'date_of_joining' => '2024-01-01',
                'department_id' => $hr->id,
                'designation_id' => $desigHRM->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ]
        );
        $hrEmployee->update([
            'employee_code' => '7C9E6679',
            'department_id' => $hr->id,
            'designation_id' => $desigHRM->id,
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=SarahConnor',
        ]);
        $hr->update(['manager_id' => $hrEmployee->id]);

        EmployeeStatusHistory::firstOrCreate(
            ['employee_id' => $hrEmployee->id, 'old_status' => 'new'],
            [
                'changed_by' => $superAdmin->id,
                'new_status' => 'active',
                'effective_date' => '2024-01-01',
                'reason' => 'Initial organization onboarding',
            ]
        );

        // 5. Senior Software Engineer (Alex Vance) - Engineering
        $devUser = User::firstOrCreate(
            ['email' => 'developer@digitake.local'],
            [
                'name' => 'Alex Vance',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=AlexVance',
                'password' => Hash::make('Password@123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $devUser->update([
            'name' => 'Alex Vance',
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=AlexVance',
        ]);
        $devUser->syncRoles(['Employee', 'Engineering']);

        $devEmployee = Employee::firstOrCreate(
            ['official_email' => 'developer@digitake.local'],
            [
                'user_id' => $devUser->id,
                'employee_code' => '9B1DEB4D',
                'first_name' => 'Alex',
                'last_name' => 'Vance',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=AlexVance',
                'phone_number' => '+1555002002',
                'date_of_joining' => '2024-03-15',
                'department_id' => $eng->id,
                'designation_id' => $desigSSE->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ]
        );
        $devEmployee->update([
            'employee_code' => '9B1DEB4D',
            'department_id' => $eng->id,
            'designation_id' => $desigSSE->id,
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=AlexVance',
        ]);

        EmployeeBankDetail::firstOrCreate(
            ['employee_id' => $devEmployee->id],
            [
                'bank_name' => 'JP Morgan Chase',
                'account_number' => '123456789012',
                'routing_code' => 'CHASUS33',
                'branch_name' => 'Downtown Manhattan',
                'account_type' => 'salary',
            ]
        );

        // 6. Finance Manager (Marcus Brody) - Finance & Accounts
        $finUser = User::firstOrCreate(
            ['email' => 'finance@digitake.local'],
            [
                'name' => 'Marcus Brody',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=MarcusBrody',
                'password' => Hash::make('Password@123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $finUser->update([
            'name' => 'Marcus Brody',
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=MarcusBrody',
        ]);
        $finUser->syncRoles(['Finance Manager', 'Finance & Accounts']);

        $finEmployee = Employee::firstOrCreate(
            ['official_email' => 'finance@digitake.local'],
            [
                'user_id' => $finUser->id,
                'employee_code' => 'A3F8B1C4',
                'first_name' => 'Marcus',
                'last_name' => 'Brody',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=MarcusBrody',
                'phone_number' => '+1555003003',
                'date_of_joining' => '2024-02-01',
                'department_id' => $fin->id,
                'designation_id' => $desigFM->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ]
        );
        $finEmployee->update([
            'employee_code' => 'A3F8B1C4',
            'department_id' => $fin->id,
            'designation_id' => $desigFM->id,
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=MarcusBrody',
        ]);
        $fin->update(['manager_id' => $finEmployee->id]);

        // 7. Business Development Manager (Rachel Green) - Sales & Marketing
        $salesUser = User::firstOrCreate(
            ['email' => 'sales@digitake.local'],
            [
                'name' => 'Rachel Green',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=RachelGreen',
                'password' => Hash::make('Password@123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $salesUser->update([
            'name' => 'Rachel Green',
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=RachelGreen',
        ]);
        $salesUser->syncRoles(['Department Manager', 'Sales & Marketing']);

        $salesEmployee = Employee::firstOrCreate(
            ['official_email' => 'sales@digitake.local'],
            [
                'user_id' => $salesUser->id,
                'employee_code' => 'D4E2F781',
                'first_name' => 'Rachel',
                'last_name' => 'Green',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=RachelGreen',
                'phone_number' => '+1555004004',
                'date_of_joining' => '2024-04-10',
                'department_id' => $sales->id,
                'designation_id' => $desigBDM->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ]
        );
        $salesEmployee->update([
            'employee_code' => 'D4E2F781',
            'department_id' => $sales->id,
            'designation_id' => $desigBDM->id,
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=RachelGreen',
        ]);
        $sales->update(['manager_id' => $salesEmployee->id]);

        // 8. DevOps & Cloud Engineer (David Miller) - Engineering
        $devopsUser = User::firstOrCreate(
            ['email' => 'devops@digitake.local'],
            [
                'name' => 'David Miller',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=DavidMiller',
                'password' => Hash::make('Password@123'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $devopsUser->update([
            'name' => 'David Miller',
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=DavidMiller',
        ]);
        $devopsUser->syncRoles(['Employee', 'Engineering']);

        $devopsEmployee = Employee::firstOrCreate(
            ['official_email' => 'devops@digitake.local'],
            [
                'user_id' => $devopsUser->id,
                'employee_code' => 'F8B3C105',
                'first_name' => 'David',
                'last_name' => 'Miller',
                'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=DavidMiller',
                'phone_number' => '+1555005005',
                'date_of_joining' => '2024-05-01',
                'department_id' => $eng->id,
                'designation_id' => $desigDevOps->id,
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ]
        );
        $devopsEmployee->update([
            'employee_code' => 'F8B3C105',
            'department_id' => $eng->id,
            'designation_id' => $desigDevOps->id,
            'avatar_url' => 'https://api.dicebear.com/9.x/lorelei/svg?seed=DavidMiller',
        ]);

        // 9. Initial Leave Records (Industry Level Demonstration)
        Leave::firstOrCreate(
            ['reason' => 'Annual executive leadership retreat & leave'],
            [
                'user_id' => $superAdmin->id,
                'applied_by_id' => $superAdmin->id,
                'approved_by_id' => $superAdmin->id,
                'acting_delegate_id' => $hrUser->id,
                'leave_type' => 'annual',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'days' => 5.0,
                'status' => 'approved',
                'is_auto_approved' => true,
            ]
        );

        Leave::firstOrCreate(
            ['reason' => 'Dental surgery procedure & recovery'],
            [
                'user_id' => $hrUser->id,
                'employee_id' => $hrEmployee->id,
                'applied_by_id' => $hrUser->id,
                'approved_by_id' => $superAdmin->id,
                'leave_type' => 'medical',
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(1)->toDateString(),
                'days' => 3.0,
                'status' => 'approved',
                'is_auto_approved' => false,
            ]
        );

        Leave::firstOrCreate(
            ['reason' => 'Family personal occasion'],
            [
                'user_id' => $devUser->id,
                'employee_id' => $devEmployee->id,
                'applied_by_id' => $devUser->id,
                'leave_type' => 'casual',
                'start_date' => now()->addDays(4)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'days' => 2.0,
                'status' => 'pending',
                'is_auto_approved' => false,
            ]
        );
    }
}
