<?php

namespace App\Modules\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\UserManagement\Requests\AssignRolesRequest;
use App\Modules\UserManagement\Requests\CreateUserRequest;
use App\Modules\UserManagement\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * List users.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $users = User::with(['roles', 'employee.department', 'employee.designation'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->role($request->input('role'));
            })
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            UserResource::collection($users),
            'Users retrieved.',
            200,
            [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ]
        );
    }

    /**
     * Create user.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user = $this->userService->create($request->validated(), $actor);
        $user->load(['roles.permissions', 'employee']);

        return ApiResponse::success(new UserResource($user), 'User created successfully.', 201);
    }

    /**
     * View user details.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);
        $user->load(['roles.permissions', 'employee.department', 'employee.designation']);

        return ApiResponse::success(new UserResource($user), 'User details retrieved.');
    }

    /**
     * Update user details.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();
        $updatedUser = $this->userService->update($user, $request->validated(), $actor);
        $updatedUser->load(['roles.permissions', 'employee']);

        return ApiResponse::success(new UserResource($updatedUser), 'User updated successfully.');
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        Gate::authorize('assignRoles', $user);

        /** @var User $actor */
        $actor = $request->user();
        $this->userService->assignRoles($user, $request->input('roles'), $actor);
        $user->load(['roles.permissions', 'employee']);

        return ApiResponse::success(new UserResource($user), 'Roles assigned successfully.');
    }

    /**
     * Deactivate user account.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deactivate', $user);

        /** @var User $actor */
        $actor = $request->user();
        $this->userService->deactivate($user, $actor);

        return ApiResponse::success(new UserResource($user), 'User deactivated successfully.');
    }
}
