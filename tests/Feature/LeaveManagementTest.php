<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $hrUser;

    protected User $employeeUser;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'admin@digitake.local',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        // Department & Designation
        $dept = Department::create(['name' => 'Human Resources', 'code' => 'HR', 'is_active' => true]);
        $desig = Designation::create(['name' => 'HR Manager', 'code' => 'HRM', 'department_id' => $dept->id, 'level' => 4]);

        // HR User
        $this->hrUser = User::factory()->create(['status' => 'active']);
        $this->hrUser->assignRole('HR Manager');

        // Regular Employee User
        $this->employeeUser = User::factory()->create(['status' => 'active']);
        $this->employeeUser->assignRole('Employee');

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-TEST-01',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'official_email' => $this->employeeUser->email,
            'phone_number' => '+1555123456',
            'date_of_joining' => '2024-01-01',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    public function test_super_admin_personal_leave_is_automatically_approved(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/v1/leaves', [
            'leave_type' => 'annual',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Executive board conference & personal time off',
            'acting_delegate_id' => $this->hrUser->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.is_auto_approved', true);
        $response->assertJsonPath('data.acting_delegate.id', $this->hrUser->id);

        $this->assertDatabaseHas('leaves', [
            'user_id' => $this->superAdmin->id,
            'status' => 'approved',
            'is_auto_approved' => true,
        ]);
    }

    public function test_regular_employee_leave_is_submitted_as_pending(): void
    {
        $response = $this->actingAs($this->employeeUser, 'sanctum')->postJson('/api/v1/leaves', [
            'leave_type' => 'casual',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'reason' => 'Family visit',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.is_auto_approved', false);

        $this->assertDatabaseHas('leaves', [
            'user_id' => $this->employeeUser->id,
            'status' => 'pending',
            'is_auto_approved' => false,
        ]);
    }

    public function test_super_admin_can_approve_pending_leave(): void
    {
        $leave = Leave::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => $this->employee->id,
            'applied_by_id' => $this->employeeUser->id,
            'leave_type' => 'casual',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'days' => 2.0,
            'reason' => 'Personal errand',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson("/api/v1/leaves/{$leave->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.approved_by.id', $this->superAdmin->id);

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'approved',
            'approved_by_id' => $this->superAdmin->id,
        ]);
    }

    public function test_super_admin_can_reject_leave_with_mandatory_reason(): void
    {
        $leave = Leave::create([
            'user_id' => $this->employeeUser->id,
            'employee_id' => $this->employee->id,
            'applied_by_id' => $this->employeeUser->id,
            'leave_type' => 'casual',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'days' => 2.0,
            'reason' => 'Personal errand',
            'status' => 'pending',
        ]);

        // Missing rejection_reason should fail validation
        $failResponse = $this->actingAs($this->superAdmin, 'sanctum')->postJson("/api/v1/leaves/{$leave->id}/reject", []);
        $failResponse->assertUnprocessable();

        // Valid rejection
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson("/api/v1/leaves/{$leave->id}/reject", [
            'rejection_reason' => 'Critical sprint release scheduled on requested dates.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $response->assertJsonPath('data.rejection_reason', 'Critical sprint release scheduled on requested dates.');
    }

    public function test_super_admin_can_apply_proxy_leave_on_behalf_of_employee(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/v1/leaves', [
            'target_user_id' => $this->employeeUser->id,
            'leave_type' => 'medical',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Hospitalized in emergency, logged by administration',
            'auto_approve' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.user_id', $this->employeeUser->id);
        $response->assertJsonPath('data.applied_by.is_proxy', true);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('leaves', [
            'user_id' => $this->employeeUser->id,
            'applied_by_id' => $this->superAdmin->id,
            'status' => 'approved',
        ]);
    }

    public function test_balances_endpoint_returns_accurate_entitlements(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/v1/leaves/balances');

        $response->assertOk();
        $response->assertJsonStructure([
            'year',
            'user',
            'entitlements' => ['casual', 'medical', 'annual'],
            'executive_exemption',
        ]);
        $response->assertJsonPath('executive_exemption', true);
    }
}
