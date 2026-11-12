<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $employeeUser;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@digitake.local',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG', 'is_active' => true]);
        $desig = Designation::create(['name' => 'Engineer', 'code' => 'ENG01', 'department_id' => $dept->id, 'level' => 2]);

        $this->employeeUser = User::factory()->create(['status' => 'active']);
        $this->employeeUser->assignRole('Employee');

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'ENG-001',
            'first_name' => 'Alice',
            'last_name' => 'Walker',
            'official_email' => $this->employeeUser->email,
            'phone_number' => '+1555000111',
            'date_of_joining' => '2024-01-01',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    public function test_user_can_clock_in_with_modality(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson('/api/v1/attendance/clock-in', [
            'modality' => 'wfh',
            'remarks' => 'Starting work from home',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.modality', 'wfh');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employeeUser->id,
            'modality' => 'wfh',
        ]);
    }

    public function test_telemetry_records_active_and_idle_seconds(): void
    {
        Sanctum::actingAs($this->employeeUser);

        // Clock in first
        $this->postJson('/api/v1/attendance/clock-in', ['modality' => 'office']);

        // Ingest telemetry batch
        $response = $this->postJson('/api/v1/attendance/telemetry', [
            'intervals' => [
                [
                    'interval_start' => now()->subMinutes(2)->toDateTimeString(),
                    'interval_end' => now()->toDateTimeString(),
                    'active_seconds' => 90,
                    'idle_seconds' => 30,
                    'break_seconds' => 0,
                    'state' => 'active',
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employeeUser->id,
            'active_minutes' => 2, // 90s rounded to minutes
            'idle_minutes' => 1,   // 30s rounded to minutes
        ]);
    }

    public function test_user_can_start_and_end_break(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $this->postJson('/api/v1/attendance/clock-in', ['modality' => 'office']);

        // Start lunch break
        $breakRes = $this->postJson('/api/v1/attendance/break/start', [
            'break_type' => 'lunch',
        ]);
        $breakRes->assertStatus(200)->assertJsonPath('status', 'success');

        // End lunch break
        $endRes = $this->postJson('/api/v1/attendance/break/end');
        $endRes->assertStatus(200)->assertJsonPath('status', 'success');
    }

    public function test_user_can_clock_out(): void
    {
        Sanctum::actingAs($this->employeeUser);

        $this->postJson('/api/v1/attendance/clock-in', ['modality' => 'office']);

        $response = $this->postJson('/api/v1/attendance/clock-out', [
            'remarks' => 'Completed day shift',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $attendance = $this->employeeUser->attendances()->latest()->first();
        $this->assertNotNull($attendance->clock_out_at);
    }

    public function test_supervisor_can_record_manual_punch_override(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/v1/attendance/manual-punch', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'clock_in_at' => now()->subHours(8)->toDateTimeString(),
            'clock_out_at' => now()->toDateTimeString(),
            'modality' => 'office',
            'status' => 'present',
            'remarks' => 'Supervisor biometric terminal missed punch override',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_manual_override', true);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employeeUser->id,
            'is_manual_override' => true,
        ]);
    }

    public function test_roster_and_stats_endpoints(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/attendance/manual-punch', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'clock_in_at' => now()->subHours(8)->toDateTimeString(),
            'modality' => 'office',
            'status' => 'present',
        ]);

        $rosterRes = $this->getJson('/api/v1/attendance/roster?date='.now()->toDateString());
        $rosterRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.count', 1);

        $statsRes = $this->getJson('/api/v1/attendance/stats?date='.now()->toDateString());
        $statsRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.present_count', 1);
    }
}
