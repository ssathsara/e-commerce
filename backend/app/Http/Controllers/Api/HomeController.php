<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Aggregated home page payload so the storefront renders with a single request.
 */
class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cards = fn (): Builder => Product::query()
            ->visible()
            ->with(['primaryImage', 'brand:id,name,slug', 'category:id,name,slug'])
            ->withCount('activeVariants');

        $featuredCategories = Category::query()
            ->active()
            ->where('is_featured', true)
            ->withCount(['products' => fn ($q) => $q->visible()])
            ->orderBy('sort_order')
            ->limit(6)
            ->get();

        return $this->success([
            'featured_categories' => CategoryResource::collection($featuredCategories)->resolve(),
            'featured_products' => ProductCardResource::collection($cards()->where('is_featured', true)->latest('id')->limit(8)->get())->resolve(),
            'new_arrivals' => ProductCardResource::collection($cards()->latest('created_at')->latest('id')->limit(8)->get())->resolve(),
            'best_sellers' => ProductCardResource::collection($cards()->where('sales_count', '>', 0)->orderByDesc('sales_count')->limit(8)->get())->resolve(),
            'on_sale' => ProductCardResource::collection($cards()->whereColumn('compare_at_price', '>', 'price')->orderByDesc('sales_count')->limit(4)->get())->resolve(),
        ]);
    }
}
