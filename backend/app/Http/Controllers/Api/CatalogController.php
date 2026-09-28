<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public category and brand browsing.
 */
class CatalogController extends Controller
{
    /** Active category tree (cached; flushed by CategoryService on every change). */
    public function categories(): JsonResponse
    {
        $tree = Cache::remember(CategoryService::TREE_CACHE_KEY, now()->addHour(), function () {
            $categories = Category::query()
                ->active()
                ->withCount(['products' => fn ($q) => $q->visible()])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            // Build the tree in memory from one query instead of recursive queries.
            $byParent = $categories->groupBy(fn (Category $c) => $c->parent_id ?? 0);
            $attach = function (Category $category) use (&$attach, $byParent) {
                $category->setRelation('children', ($byParent[$category->id] ?? collect())->each($attach)->values());

                return $category;
            };

            return CategoryResource::collection(($byParent[0] ?? collect())->each($attach)->values())->resolve();
        });

        return $this->success($tree);
    }

    public function category(string $slug): JsonResponse
    {
        $category = Category::query()
            ->active()
            ->where('slug', $slug)
            ->with(['parent', 'children' => fn ($q) => $q->active()])
            ->firstOrFail();

        return $this->success(new CategoryResource($category));
    }

    public function brands(): JsonResponse
    {
        $brands = Brand::query()
            ->active()
            ->withCount(['products' => fn ($q) => $q->visible()])
            ->orderBy('name')
            ->get();

        return $this->success(BrandResource::collection($brands));
    }

    public function brand(string $slug): JsonResponse
    {
        $brand = Brand::query()->active()->where('slug', $slug)->firstOrFail();

        return $this->success(new BrandResource($brand));
    }
}
