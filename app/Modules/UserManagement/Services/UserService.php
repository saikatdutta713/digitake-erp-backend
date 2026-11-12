<?php

namespace App\Modules\UserManagement\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new system user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
            'avatar_url' => $data['avatar_url'] ?? null,
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $this->auditService->log('user.created', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $data['roles'] ?? [],
        ], $actor);

        return $user;
    }

    /**
     * Update user details.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $oldValues = $user->only(['name', 'email', 'status']);

        $user->update($data);

        $this->auditService->log(
            'user.updated',
            $user,
            $oldValues,
            $user->only(['name', 'email', 'status']),
            $actor
        );

        return $user;
    }

    /**
     * Assign multiple roles to a user, with Super Admin protection.
     *
     * @param  list<string>  $roles
     *
     * @throws ValidationException
     */
    public function assignRoles(User $user, array $roles, User $actor): User
    {
        // If the user currently has Super Admin and the new roles omit it:
        if ($user->isSuperAdmin() && ! in_array('Super Admin', $roles, true)) {
            $superAdminCount = User::role('Super Admin')->where('status', 'active')->count();
            if ($superAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'roles' => ['Cannot remove the last active Super Admin from the system.'],
                ]);
            }
        }

        $oldRoles = $user->roles->pluck('name')->all();

        $user->syncRoles($roles);

        $this->auditService->log(
            'user.roles_assigned',
            $user,
            ['roles' => $oldRoles],
            ['roles' => $roles],
            $actor
        );

        return $user;
    }

    /**
     * Deactivate user account and revoke active tokens.
     */
    public function deactivate(User $user, User $actor): void
    {
        $oldStatus = $user->status;
        $user->update(['status' => 'inactive']);

        // Revoke all tokens
        $user->tokens()->delete();

        $this->auditService->log(
            'user.deactivated',
            $user,
            ['status' => $oldStatus],
            ['status' => 'inactive'],
            $actor
        );
    }

    /**
     * Archive user account and revoke active tokens.
     */
    public function archive(User $user, User $actor): void
    {
        $oldStatus = $user->status;
        $user->update(['status' => 'archived']);

        // Revoke all tokens
        $user->tokens()->delete();

        $this->auditService->log(
            'user.archived',
            $user,
            ['status' => $oldStatus],
            ['status' => 'archived'],
            $actor
        );
    }

    /**
     * Restore / unarchive user account.
     */
    public function unarchive(User $user, User $actor): void
    {
        $oldStatus = $user->status;
        $user->update(['status' => 'active']);

        $this->auditService->log(
            'user.unarchived',
            $user,
            ['status' => $oldStatus],
            ['status' => 'active'],
            $actor
        );
    }
}
