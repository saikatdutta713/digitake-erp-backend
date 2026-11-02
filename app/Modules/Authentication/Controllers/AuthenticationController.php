<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Authentication\Requests\ChangePasswordRequest;
use App\Modules\Authentication\Requests\ForgotPasswordRequest;
use App\Modules\Authentication\Requests\LoginRequest;
use App\Modules\Authentication\Requests\ResetPasswordRequest;
use App\Modules\Authentication\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthenticationController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate employee and issue desktop API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->input('email'),
            $request->input('password'),
            $request->input('device_uuid'),
            $request->input('device_name'),
            $request->ip() ?? '127.0.0.1'
        );

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful.');
    }

    /**
     * Revoke current token and terminate session.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Get authenticated user profile with effective roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['roles.permissions', 'employee.department', 'employee.designation']);

        return ApiResponse::success(new UserResource($user), 'User profile retrieved.');
    }

    /**
     * Change authenticated user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    /**
     * Request a password reset link/token.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->input('email');
        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        return ApiResponse::success([
            'reset_token' => $token,
        ], 'Password reset instructions have been generated.');
    }

    /**
     * Complete password reset using token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        if (! $record || ! Hash::check($request->input('token'), $record->token)) {
            return ApiResponse::error('INVALID_TOKEN', 'The password reset token is invalid or expired.', 422);
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();
        $user->update([
            'password' => Hash::make($request->input('password')),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'status' => 'active',
        ]);

        DB::table('password_reset_tokens')->where('email', $request->input('email'))->delete();

        return ApiResponse::success(null, 'Password has been reset successfully. Please log in.');
    }
}
