<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAndRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'John Doe',
                'email' => 'john.doe@digitake.test',
                'password' => 'SecurePass123!',
                'roles' => ['Employee', 'HR Executive'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john.doe@digitake.test')
            ->assertJsonPath('data.roles', ['Employee', 'HR Executive']);

        $this->assertDatabaseHas('users', ['email' => 'john.doe@digitake.test']);
    }

    public function test_user_can_hold_multiple_roles_with_aggregated_permissions(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $targetUser = User::factory()->create(['status' => 'active']);
        $targetUser->assignRole('Employee');

        // Assign multiple roles: Department Manager + HR Executive
        $response = $this->actingAs($admin)
            ->postJson("/api/v1/users/{$targetUser->id}/roles", [
                'roles' => ['Employee', 'Department Manager', 'HR Executive'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Employee'));
        $this->assertTrue($targetUser->hasRole('Department Manager'));
        $this->assertTrue($targetUser->hasRole('HR Executive'));

        // Verify aggregated effective permissions from both roles
        $this->assertTrue($targetUser->hasPermissionTo('leaves.apply')); // From Employee
        $this->assertTrue($targetUser->hasPermissionTo('leaves.approve')); // From Department Manager
        $this->assertTrue($targetUser->hasPermissionTo('employees.create')); // From HR Executive
    }

    public function test_regular_employee_cannot_assign_roles(): void
    {
        $employee = User::factory()->create(['status' => 'active']);
        $employee->assignRole('Employee');

        $target = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($employee)
            ->postJson("/api/v1/users/{$target->id}/roles", [
                'roles' => ['Super Admin'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHORIZED_ACCESS');
    }

    public function test_cannot_remove_the_last_active_super_admin(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        // Ensure there is only 1 Super Admin in the system
        $this->assertSame(1, User::role('Super Admin')->where('status', 'active')->count());

        $response = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$superAdmin->id}/roles", [
                'roles' => ['Employee'], // Attempting to drop Super Admin
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $superAdmin->refresh();
        $this->assertTrue($superAdmin->hasRole('Super Admin'));
    }

    public function test_cannot_deactivate_the_last_active_super_admin(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$superAdmin->id}/deactivate");

        // Cannot deactivate self or last super admin
        $response->assertStatus(403);
    }
}
