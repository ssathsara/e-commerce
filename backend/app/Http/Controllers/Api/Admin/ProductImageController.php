<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageRequest;
use App\Http\Requests\Admin\ReorderImagesRequest;
use App\Http\Requests\Admin\ReplaceImageRequest;
use App\Http\Requests\Admin\UpdateProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductImageService;
use Illuminate\Http\JsonResponse;

/**
 * Image routes are nested under their product and use scoped bindings,
 * so an image can only be managed through the product it belongs to.
 */
class ProductImageController extends Controller
{
    public function __construct(private readonly ProductImageService $images) {}

    public function store(ProductImageRequest $request, Product $product): JsonResponse
    {
        $this->images->upload($product, $request->file('images'));

        return $this->created($this->all($product), 'Images uploaded.');
    }

    public function update(UpdateProductImageRequest $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->images->update($image, $request->validated());

        return $this->success($this->all($product), 'Image updated.');
    }

    public function replace(ReplaceImageRequest $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->images->replace($image, $request->file('image'));

        return $this->success($this->all($product), 'Image replaced.');
    }

    public function reorder(ReorderImagesRequest $request, Product $product): JsonResponse
    {
        $this->images->reorder($product, $request->validated('image_ids'));

        return $this->success($this->all($product), 'Image order saved.');
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->images->delete($image);

        return $this->success($this->all($product), 'Image deleted.');
    }

    private function all(Product $product): array
    {
        return ProductImageResource::collection($product->images()->get())->resolve();
    }
}
