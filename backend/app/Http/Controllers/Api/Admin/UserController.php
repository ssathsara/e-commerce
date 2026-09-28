<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Resources\Admin\RoleResource;
use App\Models\User;
use App\Services\Admin\PrivilegeGuard;
use App\Services\Admin\UserManagementService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Back-office accounts (any user holding a non-customer role).
 * Role changes additionally require "assign roles" and pass PrivilegeGuard checks.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->staffMembers()
            ->with('roles:id,name')
            ->search($request->query('search'))
            ->when($request->query('role'), fn ($q, $role) => $q->role($role))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->orderBy('name')
            ->paginate($this->perPage());

        return $this->paginated($users, AdminUserResource::class);
    }

    /** Roles the current user is allowed to assign (drives the role picker). */
    public function assignableRoles(Request $request, PrivilegeGuard $guard): JsonResponse
    {
        return $this->success(RoleResource::collection($guard->assignableRoles($request->user())));
    }

    public function show(User $user): JsonResponse
    {
        return $this->success((new AdminUserResource($user->load('roles')))->withPermissions());
    }

    public function store(UserRequest $request): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::ASSIGN_ROLES), 403);

        $user = $this->users->create($request->user(), $request->validated(), $request->validated('roles'));

        return $this->created((new AdminUserResource($user->load('roles')))->withPermissions(), 'User created successfully.');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $roles = $request->has('roles') ? $request->validated('roles') : null;

        if ($roles !== null) {
            abort_unless($request->user()->can(Permissions::ASSIGN_ROLES), 403);
        }

        $user = $this->users->update($request->user(), $user, $request->safe()->except('roles'), $roles);

        return $this->success((new AdminUserResource($user->load('roles')))->withPermissions(), 'User updated successfully.');
    }

    public function updateStatus(UserStatusRequest $request, User $user): JsonResponse
    {
        $active = $request->boolean('is_active');
        $this->users->setActive($request->user(), $user, $active);

        return $this->success(new AdminUserResource($user->load('roles')), $active ? 'User activated.' : 'User deactivated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->users->delete($request->user(), $user);

        return $this->message('User deleted successfully.');
    }
}
