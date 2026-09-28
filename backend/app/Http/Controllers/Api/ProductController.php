<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Services\Catalog\ProductQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public product listing and details. All filtering/sorting/pagination is server-side.
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductQuery $products) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:140'],
            'brand' => ['nullable'],
            'brand.*' => ['string', 'max:140'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(ProductQuery::PUBLIC_SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $filters['in_stock'] = $request->boolean('in_stock');
        $filters['featured'] = $request->boolean('featured');

        if (is_string($filters['brand'] ?? null)) {
            $filters['brand'] = array_filter(explode(',', $filters['brand']));
        }

        $page = $this->products->publicListing($filters, $this->perPage(12));

        return $this->paginated($page, ProductCardResource::class);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->findVisible($slug)->load([
            'images',
            'brand',
            'category.parent',
            'activeVariants',
        ]);

        return $this->success(new ProductDetailResource($product));
    }

    /** Products from the same category (falls back to the same brand). */
    public function related(string $slug): JsonResponse
    {
        $product = $this->findVisible($slug);

        $related = Product::query()
            ->visible()
            ->whereKeyNot($product->id)
            ->where(fn ($q) => $q->where('category_id', $product->category_id)
                ->when($product->brand_id, fn ($q) => $q->orWhere('brand_id', $product->brand_id)))
            ->with(['primaryImage', 'brand:id,name,slug', 'category:id,name,slug'])
            ->withCount('activeVariants')
            ->orderByRaw('category_id = ? DESC', [$product->category_id])
            ->orderByDesc('sales_count')
            ->limit(8)
            ->get();

        return $this->success(ProductCardResource::collection($related));
    }

    public function reviews(Request $request, string $slug): JsonResponse
    {
        $product = $this->findVisible($slug);

        $reviews = $product->reviews()
            ->approved()
            ->with('user:id,name')
            ->latest()
            ->paginate($this->perPage(10));

        $distribution = $product->reviews()->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return $this->paginated($reviews, ReviewResource::class, null, [
            'rating_average' => (float) $product->rating_average,
            'rating_count' => $product->rating_count,
            'distribution' => collect(range(5, 1))->mapWithKeys(fn ($r) => [$r => (int) ($distribution[$r] ?? 0)]),
        ]);
    }

    private function findVisible(string $slug): Product
    {
        return Product::query()->visible()->where('slug', $slug)->firstOrFail();
    }
}
