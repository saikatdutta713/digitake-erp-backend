<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveStructureTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

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
        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG', 'is_active' => true]);
        $desig = Designation::create(['name' => 'Software Engineer', 'code' => 'SWE', 'department_id' => $dept->id, 'level' => 3]);

        // Regular Employee User
        $this->employeeUser = User::factory()->create(['status' => 'active']);
        $this->employeeUser->assignRole('Employee');

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-TEST-02',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'official_email' => $this->employeeUser->email,
            'phone_number' => '+1555987654',
            'date_of_joining' => '2024-01-01',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_list_leave_types(): void
    {
        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/leave-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'days_per_year',
                        'is_paid',
                        'color',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'total_categories_count',
                    'active_categories_count',
                    'total_annual_paid_quota',
                ],
            ]);

        $this->assertGreaterThanOrEqual(7, count($response->json('data')));
    }

    public function test_super_admin_can_create_custom_leave_type(): void
    {
        $payload = [
            'name' => 'Wellness & Mental Health Day',
            'code' => 'wellness',
            'days_per_year' => 6.0,
            'is_paid' => true,
            'requires_approval' => false,
            'allow_half_day' => true,
            'carry_forward_days' => 0.0,
            'applicable_gender' => 'all',
            'color' => '#14b8a6',
            'description' => 'Dedicated self-care and mental rejuvenation quota.',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/leave-types', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Wellness & Mental Health Day')
            ->assertJsonPath('data.code', 'wellness');

        $this->assertEquals(6.0, (float) $response->json('data.days_per_year'));

        $this->assertDatabaseHas('leave_types', [
            'code' => 'wellness',
            'days_per_year' => 6.0,
            'created_by_id' => $this->superAdmin->id,
        ]);
    }

    public function test_regular_employee_cannot_create_leave_type(): void
    {
        $payload = [
            'name' => 'Hacker Holiday',
            'code' => 'hacker_day',
            'days_per_year' => 30.0,
        ];

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leave-types', $payload);

        $response->assertForbidden();
    }

    public function test_super_admin_can_update_leave_type_quota_and_color(): void
    {
        LeaveType::ensureDefaultTypesExist();
        $casual = LeaveType::where('code', 'casual')->firstOrFail();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/leave-types/{$casual->id}", [
                'days_per_year' => 15.0,
                'color' => '#059669',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.color', '#059669');

        $this->assertEquals(15.0, (float) $response->json('data.days_per_year'));

        $this->assertDatabaseHas('leave_types', [
            'id' => $casual->id,
            'days_per_year' => 15.0,
            'color' => '#059669',
        ]);
    }

    public function test_super_admin_can_delete_leave_type(): void
    {
        $custom = LeaveType::create([
            'name' => 'Temporary Quarantine',
            'code' => 'quarantine',
            'days_per_year' => 14.0,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/leave-types/{$custom->id}");

        $response->assertOk();
        $this->assertSoftDeleted('leave_types', ['id' => $custom->id]);
    }

    public function test_super_admin_can_reset_leave_structure_defaults(): void
    {
        LeaveType::ensureDefaultTypesExist();
        $casual = LeaveType::where('code', 'casual')->firstOrFail();
        $casual->update(['days_per_year' => 99.0]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/leave-types/reset-defaults');

        $response->assertOk();
        $this->assertDatabaseHas('leave_types', [
            'code' => 'casual',
            'days_per_year' => 12.0,
        ]);
    }

    public function test_balances_endpoint_returns_dynamic_leave_types(): void
    {
        LeaveType::ensureDefaultTypesExist();

        // Create an extra dynamic category
        LeaveType::create([
            'name' => 'Study Leave',
            'code' => 'study',
            'days_per_year' => 7.0,
            'is_paid' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->getJson('/api/v1/leaves/balances');

        $response->assertOk()
            ->assertJsonStructure([
                'year',
                'user',
                'entitlements' => [
                    'casual',
                    'medical',
                    'annual',
                    'study' => [
                        'id',
                        'name',
                        'code',
                        'allocated',
                        'used',
                        'remaining',
                    ],
                ],
            ]);

        $this->assertEquals(7.0, (float) $response->json('entitlements.study.allocated'));
    }

    public function test_disallowed_half_day_is_rejected_by_policy(): void
    {
        LeaveType::ensureDefaultTypesExist();
        // Maternity has allow_half_day = false
        $payload = [
            'leave_type' => 'maternity',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'half_day_type' => 'first_half',
            'reason' => 'Testing disallowed half day rule on maternity leave category.',
        ];

        $response = $this->actingAs($this->employeeUser, 'sanctum')
            ->postJson('/api/v1/leaves', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['half_day_type']);
    }
}
