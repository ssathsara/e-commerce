<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\ProductStatusRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Services\Catalog\ProductQuery;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request, ProductQuery $query): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:in,low,out'],
            'sort' => ['nullable', Rule::in(ProductQuery::ADMIN_SORTS)],
        ]);

        return $this->paginated($query->adminListing($filters, $this->perPage()), AdminProductResource::class);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->toModelAttributes(), $request->user());

        return $this->created(new AdminProductResource($this->loadDetail($product)), 'Product created successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success(new AdminProductResource($this->loadDetail($product)));
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->update($product, $request->toModelAttributes(), $request->user());

        return $this->success(new AdminProductResource($this->loadDetail($product)), 'Product updated successfully.');
    }

    /** Activate / deactivate / draft. */
    public function updateStatus(ProductStatusRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->setStatus($product, ProductStatus::from($request->validated('status')));

        return $this->success(new AdminProductResource($this->loadDetail($product)), 'Product status updated.');
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return $this->message('Product deleted successfully.');
    }

    private function loadDetail(Product $product): Product
    {
        return $product->load(['images', 'variants', 'category:id,name', 'brand:id,name', 'primaryImage'])->loadCount('variants');
    }
}
