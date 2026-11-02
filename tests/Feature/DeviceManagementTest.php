<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_device_can_be_registered(): void
    {
        $response = $this->postJson('/api/v1/devices/register', [
            'device_uuid' => 'ws-hardware-uuid-999',
            'device_name' => 'Reception-Terminal-1',
            'device_type' => 'shared',
            'os_version' => 'Windows 11 Pro 23H2',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.device_uuid', 'ws-hardware-uuid-999')
            ->assertJsonPath('data.device_type', 'shared');

        $this->assertDatabaseHas('devices', [
            'device_uuid' => 'ws-hardware-uuid-999',
            'device_type' => 'shared',
        ]);
    }

    public function test_revoking_device_terminates_all_active_tokens_and_blocks_further_requests(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Employee');

        $device = Device::create([
            'device_uuid' => 'compromised-laptop-uuid',
            'device_name' => 'Compromised Laptop',
            'device_type' => 'personal',
            'app_version' => '1.0.0',
            'status' => 'active',
        ]);

        $tokenResult = $user->createToken('laptop-token');
        $session = DeviceSession::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'sanctum_token_id' => $tokenResult->accessToken->id,
            'ip_address' => '192.168.1.100',
            'session_status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Admin revokes the device
        $revokeResponse = $this->actingAs($admin)
            ->postJson("/api/v1/devices/{$device->id}/revoke");

        $revokeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked');

        // Verify token deleted
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenResult->accessToken->id]);

        // Verify device session marked revoked_by_admin
        $session->refresh();
        $this->assertSame('revoked_by_admin', $session->session_status);

        // Any request sent from this revoked hardware UUID is blocked by EnsureDeviceActiveMiddleware
        $blockedResponse = $this->withHeader('X-Device-UUID', 'compromised-laptop-uuid')
            ->getJson('/api/v1/departments');

        $blockedResponse->assertStatus(403)
            ->assertJsonPath('error.code', 'DEVICE_REVOKED');
    }

    public function test_admin_can_reactivate_revoked_device(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $device = Device::create([
            'device_uuid' => 'cleared-laptop-uuid',
            'device_name' => 'Cleared Laptop',
            'device_type' => 'personal',
            'app_version' => '1.0.0',
            'status' => 'revoked',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/devices/{$device->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $device->refresh();
        $this->assertSame('active', $device->status);
    }
}
