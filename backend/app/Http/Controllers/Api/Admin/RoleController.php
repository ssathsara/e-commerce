<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Models\User;
use App\Services\Admin\RoleService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions:id,name')->orderBy('id')->get();

        return $this->success(RoleResource::collection($this->withUserCounts($roles)));
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success($this->present($role));
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $permissions = $request->validated('permissions', []);

        if ($permissions !== []) {
            abort_unless($request->user()->can(Permissions::ASSIGN_PERMISSIONS), 403);
        }

        $role = $this->roles->create($request->user(), $request->validated('name'), $permissions);

        return $this->created($this->present($role), 'Role created successfully.');
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $permissions = $request->has('permissions') ? $request->validated('permissions', []) : null;

        if ($permissions !== null) {
            abort_unless($request->user()->can(Permissions::ASSIGN_PERMISSIONS), 403);
        }

        $role = $this->roles->update($request->user(), $role, $request->validated('name'), $permissions);

        return $this->success($this->present($role), 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->roles->delete($request->user(), $role);

        return $this->message('Role deleted successfully.');
    }

    private function present(Role $role): RoleResource
    {
        $role->load('permissions:id,name');

        return new RoleResource($this->withUserCounts(collect([$role]))->first());
    }

    /**
     * Counts role members straight from Spatie's pivot table. (Role::users() resolves
     * the user model from the current default guard, which is "sanctum" inside API
     * requests and has no user provider, so withCount('users') cannot be used here.)
     *
     * @param  Collection<int, Role>  $roles
     * @return Collection<int, Role>
     */
    private function withUserCounts(Collection $roles): Collection
    {
        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn(config('permission.column_names.role_pivot_key') ?? 'role_id', $roles->pluck('id'))
            ->selectRaw((config('permission.column_names.role_pivot_key') ?? 'role_id').' as role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return $roles->each(fn (Role $role) => $role->setAttribute('users_count', (int) ($counts[$role->id] ?? 0)));
    }
}
