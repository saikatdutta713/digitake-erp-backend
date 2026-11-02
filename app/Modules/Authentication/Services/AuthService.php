<?php

namespace App\Modules\Authentication\Services;

use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Authenticate user credentials and issue an isolated API token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(
        string $email,
        string $password,
        ?string $deviceUuid,
        ?string $deviceName,
        string $ipAddress
    ): array {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->auditService->log('auth.failed_attempt', null, null, ['email' => $email]);
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->isLocked()) {
            $this->auditService->log('auth.locked_access_attempt', $user);
            throw ValidationException::withMessages([
                'email' => ['This account is temporarily locked due to multiple failed login attempts.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive or suspended. Please contact your administrator.'],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $failedAttempts = $user->failed_login_attempts + 1;
            $updates = ['failed_login_attempts' => $failedAttempts];

            if ($failedAttempts >= 5) {
                $updates['status'] = 'locked';
                $updates['locked_until'] = now()->addMinutes(15);
            }

            $user->update($updates);
            $this->auditService->log('auth.failed_password', $user, null, ['failed_attempts' => $failedAttempts]);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Reset failed login counters
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        // Resolve device if UUID supplied
        $device = null;
        if ($deviceUuid) {
            $device = Device::where('device_uuid', $deviceUuid)->first();
            if (! $device && $deviceName) {
                $device = Device::create([
                    'device_uuid' => $deviceUuid,
                    'device_name' => $deviceName,
                    'device_type' => 'personal',
                    'platform' => 'windows',
                    'app_version' => '1.0.0',
                    'status' => 'active',
                    'registered_by' => $user->id,
                    'last_seen_at' => now(),
                ]);
            } elseif ($device) {
                $device->updateQuietly(['last_seen_at' => now()]);
            }
        }

        // Issue Sanctum token
        $token = $user->createToken('desktop-auth-token');

        // Track device session
        if ($device) {
            DeviceSession::create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'sanctum_token_id' => $token->accessToken->id,
                'ip_address' => $ipAddress,
                'session_status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        $this->auditService->log('auth.login', $user, null, [
            'ip' => $ipAddress,
            'device_uuid' => $deviceUuid,
        ], $user);

        $user->load(['roles.permissions', 'employee.department', 'employee.designation']);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Revoke active token and terminate device session.
     */
    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            DeviceSession::where('sanctum_token_id', $currentToken->id)
                ->where('session_status', 'active')
                ->update([
                    'session_status' => 'logged_out',
                    'ended_at' => now(),
                ]);

            $currentToken->delete();
        }

        $this->auditService->log('auth.logout', $user, null, null, $user);
    }
}
