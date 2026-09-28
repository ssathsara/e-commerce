<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Server-side search, filtering, sorting and pagination for product listings.
 * All filtering happens in SQL; the client only ever receives one page.
 */
class ProductQuery
{
    public const PUBLIC_SORTS = ['newest', 'price_asc', 'price_desc', 'popular', 'rating'];

    public const ADMIN_SORTS = ['newest', 'oldest', 'name', 'price_asc', 'price_desc', 'stock_asc', 'stock_desc', 'popular'];

    /** Columns needed by listing cards (keeps rows small). */
    private const LISTING_COLUMNS = [
        'id', 'category_id', 'brand_id', 'name', 'slug', 'sku', 'short_description', 'price', 'compare_at_price',
        'stock_quantity', 'low_stock_threshold', 'status', 'is_featured', 'rating_average', 'rating_count',
        'sales_count', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * @param  array{search?: string, category?: string, brand?: string|array, min_price?: float|string, max_price?: float|string, in_stock?: bool, rating?: int, featured?: bool, sort?: string}  $filters
     */
    public function publicListing(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->visible()
            ->select(self::LISTING_COLUMNS)
            ->with(['primaryImage', 'brand:id,name,slug', 'category:id,name,slug'])
            ->withCount('activeVariants')
            ->search($filters['search'] ?? null);

        if (! empty($filters['category'])) {
            $category = Category::query()->active()->where('slug', $filters['category'])->first();
            // Unknown category: return an empty result rather than ignoring the filter.
            $query->whereIn('category_id', $category?->descendantAndSelfIds() ?? [0]);
        }

        if (! empty($filters['brand'])) {
            $slugs = (array) $filters['brand'];
            $query->whereIn('brand_id', Brand::query()->active()->whereIn('slug', $slugs)->select('id'));
        }

        $this->applyPriceRange($query, $filters);

        if (! empty($filters['in_stock'])) {
            $query->inStock();
        }

        if (! empty($filters['rating'])) {
            $query->where('rating_average', '>=', (int) $filters['rating']);
        }

        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{search?: string, status?: string, category_id?: int, brand_id?: int, stock?: string, sort?: string}  $filters
     */
    public function adminListing(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Product::query()
            ->select(self::LISTING_COLUMNS)
            ->with(['primaryImage', 'brand:id,name', 'category:id,name'])
            ->withCount('variants')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
            ->when($filters['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id));

        match ($filters['stock'] ?? null) {
            'in' => $query->inStock(),
            'low' => $query->lowStock(),
            'out' => $query->outOfStock(),
            default => null,
        };

        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate($perPage)->withQueryString();
    }

    private function applyPriceRange(Builder $query, array $filters): void
    {
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price', '>=', Money::toCents($filters['min_price']));
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price', '<=', Money::toCents($filters['max_price']));
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'popular' => $query->orderByDesc('sales_count')->orderByDesc('id'),
            'rating' => $query->orderByDesc('rating_average')->orderByDesc('rating_count')->orderByDesc('id'),
            'stock_asc' => $query->orderBy('stock_quantity')->orderByDesc('id'),
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderByDesc('id'),
            default => $query->latest('created_at')->orderByDesc('id'),
        };
    }
}
