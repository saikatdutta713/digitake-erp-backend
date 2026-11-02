<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAndDesignationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_department_and_designation(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $deptResponse = $this->actingAs($admin)
            ->postJson('/api/v1/departments', [
                'name' => 'Engineering',
                'code' => 'ENG',
            ]);

        $deptResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'ENG');

        $deptId = $deptResponse->json('data.id');

        $desigResponse = $this->actingAs($admin)
            ->postJson('/api/v1/designations', [
                'name' => 'Senior Software Engineer',
                'code' => 'SSE',
                'department_id' => $deptId,
                'level' => 3,
            ]);

        $desigResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SSE')
            ->assertJsonPath('data.level', 3);
    }

    public function test_unauthorized_user_cannot_create_department(): void
    {
        $employee = User::factory()->create(['status' => 'active']);
        $employee->assignRole('Employee');

        $response = $this->actingAs($employee)
            ->postJson('/api/v1/departments', [
                'name' => 'Marketing',
                'code' => 'MKT',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'UNAUTHORIZED_ACCESS');
    }

    public function test_cannot_delete_department_with_active_employees(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $dept = Department::create(['name' => 'Sales', 'code' => 'SALES']);
        $desig = Designation::create(['name' => 'Executive', 'code' => 'SALES-EXEC', 'department_id' => $dept->id]);

        $user = User::factory()->create(['status' => 'active']);
        Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'official_email' => 'jane.doe@digitake.test',
            'phone_number' => '+1234567890',
            'date_of_joining' => '2025-01-01',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/departments/{$dept->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DEPARTMENT_HAS_EMPLOYEES');

        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }
}
