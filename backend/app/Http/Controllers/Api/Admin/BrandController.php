<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brands) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $search = $request->query('search');

        $brands = Brand::query()
            ->withCount('products')
            ->when($search, fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->orderBy('name')
            ->paginate($this->perPage(25));

        return $this->paginated($brands, BrandResource::class);
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $brand = $this->brands->create($request->toModelAttributes(), $request->file('logo'));

        return $this->created(new BrandResource($brand), 'Brand created successfully.');
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->success(new BrandResource($brand->loadCount('products')));
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brands->update($brand, $request->toModelAttributes(), $request->file('logo'));

        return $this->success(new BrandResource($brand), 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->delete($brand);

        return $this->message('Brand deleted successfully.');
    }
}
