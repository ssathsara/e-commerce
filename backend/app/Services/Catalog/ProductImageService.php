<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Invariant: a product with images has exactly one primary image.
 */
class ProductImageService
{
    public function __construct(private readonly ImageStorage $storage) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<ProductImage>
     */
    public function upload(Product $product, array $files): array
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($product, $files, &$paths) {
                $nextOrder = (int) $product->images()->max('sort_order') + 1;
                $hasPrimary = $product->images()->where('is_primary', true)->exists();
                $created = [];

                foreach ($files as $file) {
                    $paths[] = $path = $this->storage->store($file, "products/{$product->id}");

                    $created[] = $product->images()->create([
                        'path' => $path,
                        'alt_text' => $product->name,
                        'sort_order' => $nextOrder++,
                        'is_primary' => ! $hasPrimary,
                    ]);

                    $hasPrimary = true;
                }

                return $created;
            });
        } catch (\Throwable $e) {
            // Do not leave orphaned files behind if the DB work failed.
            foreach ($paths as $path) {
                $this->storage->delete($path);
            }

            throw $e;
        }
    }

    public function replace(ProductImage $image, UploadedFile $file): ProductImage
    {
        $oldPath = $image->path;
        $image->path = $this->storage->store($file, "products/{$image->product_id}");
        $image->save();

        $this->storage->delete($oldPath);

        return $image;
    }

    public function update(ProductImage $image, array $data): ProductImage
    {
        return DB::transaction(function () use ($image, $data) {
            if (array_key_exists('alt_text', $data)) {
                $image->alt_text = $data['alt_text'];
            }

            if (! empty($data['is_primary'])) {
                ProductImage::query()->where('product_id', $image->product_id)->update(['is_primary' => false]);
                $image->is_primary = true;
            }

            $image->save();

            return $image;
        });
    }

    /**
     * @param  list<int>  $orderedIds  Image ids of the product in the desired order.
     */
    public function reorder(Product $product, array $orderedIds): void
    {
        DB::transaction(function () use ($product, $orderedIds) {
            foreach (array_values($orderedIds) as $position => $id) {
                ProductImage::query()->where('product_id', $product->id)->whereKey($id)->update(['sort_order' => $position]);
            }
        });
    }

    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->delete();

            if ($image->is_primary) {
                ProductImage::query()
                    ->where('product_id', $image->product_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(1)
                    ->update(['is_primary' => true]);
            }
        });

        $this->storage->delete($image->path);
    }
}
