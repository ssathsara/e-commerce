<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Services\Admin\RoleService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions:id,name')->withCount('users')->orderBy('id')->get();

        return $this->success(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success(new RoleResource($role->load('permissions:id,name')->loadCount('users')));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $permissions = $request->validated('permissions', []);

        if ($permissions !== []) {
            abort_unless($request->user()->can(Permissions::ASSIGN_PERMISSIONS), 403);
        }

        $role = $this->roles->create($request->user(), $request->validated('name'), $permissions);

        return $this->created(new RoleResource($role->load('permissions:id,name')->loadCount('users')), 'Role created successfully.');
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $permissions = $request->has('permissions') ? $request->validated('permissions', []) : null;

        if ($permissions !== null) {
            abort_unless($request->user()->can(Permissions::ASSIGN_PERMISSIONS), 403);
        }

        $role = $this->roles->update($request->user(), $role, $request->validated('name'), $permissions);

        return $this->success(new RoleResource($role->loadCount('users')), 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->roles->delete($request->user(), $role);

        return $this->message('Role deleted successfully.');
    }
}
