<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $search = $request->query('search');

        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount(['products', 'children'])
            ->when($search, fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($request->query('status'), fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->when($request->filled('parent_id'), fn ($q) => $q->where('parent_id', $request->integer('parent_id')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage(25));

        return $this->paginated($categories, CategoryResource::class);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->toModelAttributes(), $request->file('image'));

        return $this->created(new CategoryResource($category->load('parent')), 'Category created successfully.');
    }

    public function show(Category $category): JsonResponse
    {
        return $this->success(new CategoryResource($category->load(['parent', 'children'])->loadCount(['products', 'children'])));
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->toModelAttributes(), $request->file('image'));

        return $this->success(new CategoryResource($category->load('parent')), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return $this->message('Category deleted successfully.');
    }
}
