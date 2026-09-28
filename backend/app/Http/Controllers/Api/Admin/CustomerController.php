<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer accounts (users with the Customer role).
 */
class CustomerController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:newest,oldest,name,spent'],
        ]);

        $customers = User::query()
            ->customers()
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->withCount('orders')
            ->withSum(['orders' => fn ($q) => $q->revenue()], 'grand_total');

        match ($request->query('sort')) {
            'oldest' => $customers->oldest('id'),
            'name' => $customers->orderBy('name'),
            'spent' => $customers->orderByDesc('orders_sum_grand_total'),
            default => $customers->latest('id'),
        };

        return $this->paginated($customers->paginate($this->perPage()), AdminUserResource::class);
    }

    public function show(User $customer): JsonResponse
    {
        $this->ensureCustomer($customer);

        $customer->loadCount('orders')
            ->loadSum(['orders' => fn ($q) => $q->revenue()], 'grand_total')
            ->load(['roles', 'addresses']);

        $recentOrders = $customer->orders()->withCount('items')->latest('id')->limit(10)->get();

        return $this->success([
            ...(new AdminUserResource($customer))->resolve(),
            'addresses' => AddressResource::collection($customer->addresses)->resolve(),
            'recent_orders' => AdminOrderResource::collection($recentOrders)->resolve(),
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $customer = $this->users->create($request->user(), $request->validated(), [RoleName::Customer->value]);

        return $this->created(new AdminUserResource($customer->load('roles')), 'Customer created successfully.');
    }

    public function update(UserRequest $request, User $customer): JsonResponse
    {
        $this->ensureCustomer($customer);

        // Roles are not managed from the customers module.
        $customer = $this->users->update($request->user(), $customer, $request->safe()->except('roles'), null);

        return $this->success(new AdminUserResource($customer->load('roles')), 'Customer updated successfully.');
    }

    public function updateStatus(UserStatusRequest $request, User $customer): JsonResponse
    {
        $this->ensureCustomer($customer);

        $active = $request->boolean('is_active');
        $this->users->setActive($request->user(), $customer, $active);

        return $this->success(new AdminUserResource($customer->load('roles')), $active ? 'Customer activated.' : 'Customer deactivated.');
    }

    public function destroy(Request $request, User $customer): JsonResponse
    {
        $this->ensureCustomer($customer);

        $this->users->delete($request->user(), $customer);

        return $this->message('Customer deleted successfully.');
    }

    /** This module only manages customers; staff accounts live under /admin/users. */
    private function ensureCustomer(User $user): void
    {
        abort_unless($user->hasRole(RoleName::Customer->value), 404);
    }
}
