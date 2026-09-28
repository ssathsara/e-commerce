<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\Account\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default_shipping')
            ->latest('id')
            ->get();

        return $this->success(AddressResource::collection($addresses));
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $address = $this->addresses->create($request->user(), $request->validated());

        return $this->created(new AddressResource($address), 'Address saved.');
    }

    public function show(Address $address): JsonResponse
    {
        Gate::authorize('view', $address);

        return $this->success(new AddressResource($address));
    }

    public function update(AddressRequest $request, Address $address): JsonResponse
    {
        Gate::authorize('update', $address);

        $address = $this->addresses->update($address, $request->validated());

        return $this->success(new AddressResource($address), 'Address updated.');
    }

    public function destroy(Address $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $this->addresses->delete($address);

        return $this->message('Address deleted.');
    }
}
