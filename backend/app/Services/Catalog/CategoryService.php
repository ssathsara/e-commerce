<?php

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Services\Media\ImageStorage;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /** Cache key for the public category tree (flushed on every change). */
    public const TREE_CACHE_KEY = 'catalog:category-tree';

    public function __construct(private readonly ImageStorage $images) {}

    public function create(array $data, ?UploadedFile $image): Category
    {
        $category = DB::transaction(function () use ($data, $image) {
            $category = new Category(Arr::except($data, ['image', 'remove_image']));

            if ($image) {
                $category->image_path = $this->images->store($image, 'categories');
            }

            $category->save();

            return $category;
        });

        $this->flushCache();
        AuditLog::info('category.created', ['category_id' => $category->id]);

        return $category;
    }

    public function update(Category $category, array $data, ?UploadedFile $image): Category
    {
        if (! empty($data['parent_id']) && in_array((int) $data['parent_id'], $category->descendantAndSelfIds(), true)) {
            throw BusinessRuleException::unprocessable('A category cannot be moved under itself or one of its subcategories.', 'parent_id');
        }

        $oldImage = $category->image_path;
        $category->fill(Arr::except($data, ['image', 'remove_image']));

        if ($image) {
            $category->image_path = $this->images->store($image, 'categories');
        } elseif (! empty($data['remove_image'])) {
            $category->image_path = null;
        }

        $category->save();

        if ($oldImage !== $category->image_path) {
            $this->images->delete($oldImage);
        }

        $this->flushCache();
        AuditLog::info('category.updated', ['category_id' => $category->id]);

        return $category;
    }

    /**
     * Deletion is refused while products or subcategories still reference the
     * category: the admin must reassign them first, so no product is left orphaned.
     */
    public function delete(Category $category): void
    {
        $productCount = $category->products()->count();
        if ($productCount > 0) {
            throw BusinessRuleException::conflict("This category still has {$productCount} product(s). Move them to another category first.");
        }

        if ($category->children()->exists()) {
            throw BusinessRuleException::conflict('This category has subcategories. Move or delete them first.');
        }

        $category->delete();

        $this->flushCache();
        AuditLog::info('category.deleted', ['category_id' => $category->id]);
    }

    public function flushCache(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }
}
