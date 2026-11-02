<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Department $engDept;

    protected Department $salesDept;

    protected Designation $engDesig;

    protected Designation $salesDesig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->engDept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->salesDept = Department::create(['name' => 'Sales', 'code' => 'SALES']);

        $this->engDesig = Designation::create(['name' => 'Developer', 'code' => 'DEV', 'department_id' => $this->engDept->id]);
        $this->salesDesig = Designation::create(['name' => 'Sales Rep', 'code' => 'REP', 'department_id' => $this->salesDept->id]);
    }

    public function test_hr_manager_can_create_employee_with_auto_generated_code_and_user(): void
    {
        $hrManager = User::factory()->create(['status' => 'active']);
        $hrManager->assignRole('HR Manager');

        $response = $this->actingAs($hrManager)
            ->postJson('/api/v1/employees', [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'official_email' => 'alice.smith@digitake.test',
                'phone_number' => '+1555123456',
                'date_of_joining' => '2025-02-01',
                'department_id' => $this->engDept->id,
                'designation_id' => $this->engDesig->id,
                'employment_type' => 'full_time',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_code', 'EMP-0001')
            ->assertJsonPath('data.official_email', 'alice.smith@digitake.test');

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-0001',
            'official_email' => 'alice.smith@digitake.test',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice.smith@digitake.test',
        ]);

        $this->assertDatabaseHas('employee_status_history', [
            'new_status' => 'active',
        ]);
    }

    public function test_department_manager_is_scoped_strictly_to_their_department(): void
    {
        // Create Engineering Department Manager
        $mgrUser = User::factory()->create(['status' => 'active']);
        $mgrUser->assignRole('Department Manager');

        $mgrEmployee = Employee::create([
            'user_id' => $mgrUser->id,
            'employee_code' => 'EMP-0099',
            'first_name' => 'Manager',
            'last_name' => 'Eng',
            'official_email' => 'mgr.eng@digitake.test',
            'phone_number' => '+1555999999',
            'date_of_joining' => '2024-01-01',
            'department_id' => $this->engDept->id,
            'designation_id' => $this->engDesig->id,
        ]);
        $this->engDept->update(['manager_id' => $mgrEmployee->id]);

        // Create an Engineering employee
        $engEmpUser = User::factory()->create(['status' => 'active']);
        $engEmp = Employee::create([
            'user_id' => $engEmpUser->id,
            'employee_code' => 'EMP-0101',
            'first_name' => 'Bob',
            'last_name' => 'Eng',
            'official_email' => 'bob.eng@digitake.test',
            'phone_number' => '+1555101010',
            'date_of_joining' => '2025-01-15',
            'department_id' => $this->engDept->id,
            'designation_id' => $this->engDesig->id,
        ]);

        // Create a Sales employee
        $salesEmpUser = User::factory()->create(['status' => 'active']);
        $salesEmp = Employee::create([
            'user_id' => $salesEmpUser->id,
            'employee_code' => 'EMP-0202',
            'first_name' => 'Charlie',
            'last_name' => 'Sales',
            'official_email' => 'charlie.sales@digitake.test',
            'phone_number' => '+1555202020',
            'date_of_joining' => '2025-01-15',
            'department_id' => $this->salesDept->id,
            'designation_id' => $this->salesDesig->id,
        ]);

        // Manager lists employees - Sales employee must NOT be present
        $listResponse = $this->actingAs($mgrUser)
            ->getJson('/api/v1/employees');

        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id')->all();

        $this->assertContains($engEmp->id, $ids);
        $this->assertNotContains($salesEmp->id, $ids);

        // Direct show request to the Sales employee must be rejected with 403
        $showSalesResponse = $this->actingAs($mgrUser)
            ->getJson("/api/v1/employees/{$salesEmp->id}");

        $showSalesResponse->assertStatus(403);
    }

    public function test_confidential_bank_details_access_isolation(): void
    {
        // Create an employee with bank details
        $empUser = User::factory()->create(['status' => 'active']);
        $empUser->assignRole('Employee');
        $employee = Employee::create([
            'user_id' => $empUser->id,
            'employee_code' => 'EMP-0303',
            'first_name' => 'Dave',
            'last_name' => 'Confidential',
            'official_email' => 'dave@digitake.test',
            'phone_number' => '+1555303030',
            'date_of_joining' => '2025-01-01',
            'department_id' => $this->engDept->id,
            'designation_id' => $this->engDesig->id,
        ]);

        EmployeeBankDetail::create([
            'employee_id' => $employee->id,
            'bank_name' => 'Citibank',
            'account_number' => '9876543210',
            'routing_code' => 'CITI0001234',
            'account_type' => 'salary',
        ]);

        // Unauthorized employee trying to view Dave's bank details
        $otherUser = User::factory()->create(['status' => 'active']);
        $otherUser->assignRole('Employee');

        $unauthResponse = $this->actingAs($otherUser)
            ->getJson("/api/v1/employees/{$employee->id}/bank");

        $unauthResponse->assertStatus(403);

        // Accountant trying to view Dave's bank details (Accountant is restricted)
        $accountantUser = User::factory()->create(['status' => 'active']);
        $accountantUser->assignRole('Accountant');

        $acctResponse = $this->actingAs($accountantUser)
            ->getJson("/api/v1/employees/{$employee->id}/bank");

        $acctResponse->assertStatus(403);

        // Dave viewing his own bank details
        $selfResponse = $this->actingAs($empUser)
            ->getJson("/api/v1/employees/{$employee->id}/bank");

        $selfResponse->assertStatus(200)
            ->assertJsonPath('data.account_number', '9876543210');

        // Finance Manager viewing Dave's bank details
        $finManager = User::factory()->create(['status' => 'active']);
        $finManager->assignRole('Finance Manager');

        $finResponse = $this->actingAs($finManager)
            ->getJson("/api/v1/employees/{$employee->id}/bank");

        $finResponse->assertStatus(200)
            ->assertJsonPath('data.bank_name', 'Citibank');
    }

    public function test_status_update_to_terminated_revokes_system_access(): void
    {
        $hrManager = User::factory()->create(['status' => 'active']);
        $hrManager->assignRole('HR Manager');

        $empUser = User::factory()->create(['status' => 'active']);
        $token = $empUser->createToken('active-token');

        $employee = Employee::create([
            'user_id' => $empUser->id,
            'employee_code' => 'EMP-0404',
            'first_name' => 'Eve',
            'last_name' => 'Exiting',
            'official_email' => 'eve@digitake.test',
            'phone_number' => '+1555404040',
            'date_of_joining' => '2024-05-01',
            'department_id' => $this->engDept->id,
            'designation_id' => $this->engDesig->id,
        ]);

        $response = $this->actingAs($hrManager)
            ->postJson("/api/v1/employees/{$employee->id}/status", [
                'status' => 'terminated',
                'effective_date' => '2026-11-01',
                'reason' => 'Contract completed',
            ]);

        $response->assertStatus(200);

        $employee->refresh();
        $this->assertSame('terminated', $employee->employment_status);

        $empUser->refresh();
        $this->assertSame('inactive', $empUser->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
