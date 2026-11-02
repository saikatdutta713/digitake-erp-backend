<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@digitake.test',
            'password' => Hash::make('SecretPass123!'),
            'status' => 'active',
        ]);
        $user->assignRole('Employee');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'developer@digitake.test',
            'password' => 'SecretPass123!',
            'device_uuid' => 'dev-uuid-12345',
            'device_name' => 'Workstation-Dev-1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('devices', ['device_uuid' => 'dev-uuid-12345']);
        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $user->id,
            'session_status' => 'active',
        ]);
    }

    public function test_login_fails_with_invalid_password_and_increments_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@digitake.test',
            'password' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'failed_login_attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'developer@digitake.test',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $user->refresh();
        $this->assertSame(1, $user->failed_login_attempts);
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'developer@digitake.test',
            'password' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
            'failed_login_attempts' => 4,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'developer@digitake.test',
            'password' => 'WrongPasswordAgain!',
        ]);

        $response->assertStatus(422);

        $user->refresh();
        $this->assertSame(5, $user->failed_login_attempts);
        $this->assertSame('locked', $user->status);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->isLocked());
    }

    public function test_locked_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'locked@digitake.test',
            'password' => Hash::make('CorrectPassword123!'),
            'status' => 'locked',
            'locked_until' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@digitake.test',
            'password' => 'CorrectPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_access_me_profile(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('HR Manager');

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['HR Manager']);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_logout_revokes_token_and_ends_device_session(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $device = Device::create([
            'device_uuid' => 'test-device-uuid',
            'device_name' => 'Test PC',
            'device_type' => 'personal',
            'app_version' => '1.0.0',
            'status' => 'active',
        ]);

        $tokenResult = $user->createToken('test-token');
        $session = DeviceSession::create([
            'device_id' => $device->id,
            'user_id' => $user->id,
            'sanctum_token_id' => $tokenResult->accessToken->id,
            'ip_address' => '127.0.0.1',
            'session_status' => 'active',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify token deleted
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenResult->accessToken->id]);

        // Verify device session ended
        $session->refresh();
        $this->assertSame('logged_out', $session->session_status);
        $this->assertNotNull($session->ended_at);

        // Verify subsequent request with old token fails
        app('auth')->forgetGuards();
        $afterLogoutResponse = $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $afterLogoutResponse->assertStatus(401);
    }
}
