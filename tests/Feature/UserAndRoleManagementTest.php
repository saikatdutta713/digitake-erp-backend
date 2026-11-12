<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
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

    public function test_super_admin_can_retrieve_module_grouped_permissions(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->getJson('/api/v1/permissions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'all',
                    'grouped',
                ],
            ]);
    }

    public function test_super_admin_can_update_role_permissions(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $role = Role::findByName('HR Executive');
        $newPermissions = ['employees.view', 'leaves.view', 'leaves.approve'];

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/v1/roles/{$role->id}/permissions", [
                'permissions' => $newPermissions,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permissions', $newPermissions);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('employees.view'));
        $this->assertFalse($role->hasPermissionTo('employees.archive'));
    }

    public function test_cannot_modify_super_admin_fixed_permissions(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $role = Role::findByName('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/v1/roles/{$role->id}/permissions", [
                'permissions' => ['employees.view'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'IMMUTABLE_ROLE');
    }

    public function test_super_admin_can_create_admin_with_avatar(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/v1/users', [
                'name' => 'Alice Admin',
                'email' => 'alice.admin@digitake.test',
                'password' => 'AdminPass123!',
                'roles' => ['Admin'],
                'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Alice Admin')
            ->assertJsonPath('data.avatar_url', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150')
            ->assertJsonPath('data.roles', ['Admin']);

        $this->assertDatabaseHas('users', [
            'email' => 'alice.admin@digitake.test',
            'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150',
        ]);
    }

    public function test_super_admin_can_create_custom_role(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->postJson('/api/v1/roles', [
                'name' => 'QA Lead',
                'description' => 'Quality Assurance Team Lead',
                'permissions' => ['employees.view', 'reports.view'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'QA Lead')
            ->assertJsonPath('data.is_system_role', false)
            ->assertJsonPath('data.permissions', ['employees.view', 'reports.view']);

        $this->assertDatabaseHas('roles', [
            'name' => 'QA Lead',
            'is_system_role' => false,
        ]);
    }

    public function test_super_admin_can_update_custom_role(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $role = Role::create([
            'name' => 'Support Agent',
            'guard_name' => 'web',
            'description' => 'Customer support tier 1',
            'is_system_role' => false,
        ]);

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'Support Specialist',
                'description' => 'Customer support tier 2 specialist',
                'permissions' => ['employees.view'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Support Specialist')
            ->assertJsonPath('data.description', 'Customer support tier 2 specialist');

        $role->refresh();
        $this->assertSame('Support Specialist', $role->name);
    }

    public function test_cannot_rename_system_predefined_role(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $superAdminRole = Role::findByName('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/v1/roles/{$superAdminRole->id}", [
                'name' => 'Renamed Super Admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SYSTEM_ROLE_PROTECTED');
    }

    public function test_super_admin_can_delete_custom_role(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $role = Role::create([
            'name' => 'Temporary Contractor',
            'guard_name' => 'web',
            'is_system_role' => false,
            'role_type' => 'custom',
        ]);

        $response = $this->actingAs($superAdmin)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('roles', ['name' => 'Temporary Contractor']);
    }

    public function test_cannot_delete_system_predefined_role(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $superAdminRole = Role::findByName('Super Admin');

        $response = $this->actingAs($superAdmin)
            ->deleteJson("/api/v1/roles/{$superAdminRole->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SYSTEM_ROLE_PROTECTED');

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $customRole = Role::create([
            'name' => 'Operations Analyst',
            'guard_name' => 'web',
            'is_system_role' => false,
            'role_type' => 'custom',
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Operations Analyst');

        $response = $this->actingAs($superAdmin)
            ->deleteJson("/api/v1/roles/{$customRole->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ROLE_IN_USE');

        $this->assertDatabaseHas('roles', ['name' => 'Operations Analyst']);
    }

    public function test_super_admin_can_archive_and_unarchive_user(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $targetUser = User::factory()->create(['status' => 'active']);
        $targetUser->assignRole('Employee');
        $targetUser->createToken('test-token');

        $this->assertCount(1, $targetUser->tokens);

        // Archive
        $archiveResponse = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$targetUser->id}/archive");

        $archiveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'archived');

        $targetUser->refresh();
        $this->assertSame('archived', $targetUser->status);
        $this->assertCount(0, $targetUser->tokens); // Tokens revoked

        // Unarchive
        $unarchiveResponse = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$targetUser->id}/unarchive");

        $unarchiveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $targetUser->refresh();
        $this->assertSame('active', $targetUser->status);
    }

    public function test_cannot_archive_super_admin_or_self(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        // Cannot archive self
        $selfResponse = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$superAdmin->id}/archive");

        $selfResponse->assertStatus(403)
            ->assertJsonPath('error.code', 'SELF_ARCHIVE_NOT_ALLOWED');

        // Cannot archive another super admin
        $anotherAdmin = User::factory()->create(['status' => 'active']);
        $anotherAdmin->assignRole('Super Admin');

        $superResponse = $this->actingAs($superAdmin)
            ->postJson("/api/v1/users/{$anotherAdmin->id}/archive");

        $superResponse->assertStatus(403)
            ->assertJsonPath('error.code', 'SUPER_ADMIN_ARCHIVE_PROTECTED');
    }

    public function test_super_admin_can_filter_users_by_archived_status(): void
    {
        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        User::factory()->create(['status' => 'active', 'name' => 'Active Member']);
        User::factory()->create(['status' => 'archived', 'name' => 'Archived Member']);

        $response = $this->actingAs($superAdmin)
            ->getJson('/api/v1/users?status=archived');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertSame('archived', $item['status']);
        }
    }
}
