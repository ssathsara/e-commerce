<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustmentRequest;
use App\Http\Resources\Admin\AdminProductResource;
use App\Http\Resources\Admin\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Catalog\ProductQuery;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    /** Stock levels per product (and per variant). */
    public function index(Request $request, ProductQuery $query): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', 'in:in,low,out'],
            'sort' => ['nullable', Rule::in(ProductQuery::ADMIN_SORTS)],
        ]);
        $filters['sort'] ??= 'stock_asc';

        $page = $query->adminListing($filters, $this->perPage());
        $page->getCollection()->load('variants');

        return $this->paginated($page, AdminProductResource::class, null, [
            'low_stock_count' => Product::query()->lowStock()->count(),
            'out_of_stock_count' => Product::query()->outOfStock()->count(),
        ]);
    }

    public function adjust(InventoryAdjustmentRequest $request, InventoryService $inventory): JsonResponse
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));
        $variant = $request->validated('variant_id') ? $product->variants()->findOrFail($request->validated('variant_id')) : null;

        if ($variant === null && $product->variants()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This product has variants. Please choose which variant to adjust.',
                'errors' => ['variant_id' => ['Please choose a variant.']],
            ], 422);
        }

        $movement = $inventory->adjust(
            $product,
            $variant,
            (int) $request->validated('quantity'),
            InventoryMovementType::from($request->validated('type')),
            $request->user(),
            $request->validated('note'),
        );

        return $this->created(
            new InventoryMovementResource($movement->load(['product:id,name,sku', 'variant:id,name,sku', 'user:id,name'])),
            'Stock updated.',
        );
    }

    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(InventoryMovementType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'variant:id,name,sku', 'order:id,order_number', 'user:id,name'])
            ->when($request->query('product_id'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->latest('id')
            ->paginate($this->perPage(25));

        return $this->paginated($movements, InventoryMovementResource::class);
    }
}
