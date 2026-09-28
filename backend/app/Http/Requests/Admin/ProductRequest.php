<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update product (PUT semantics: the full product is submitted).
 * Money is received in major units and converted to cents by toModelAttributes().
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
        }
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $isCreate = $product === null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'compare_at_price' => ['nullable', 'numeric', 'max:9999999', 'decimal:0,2', 'gt:price'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'variants' => ['sometimes', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-_]+$/', 'distinct:ignore_case'],
            'variants.*.options' => ['nullable', 'array', 'max:5'],
            'variants.*.options.*' => ['string', 'max:60'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'max:9999999', 'decimal:0,2'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];

        // Opening stock can only be set on creation; afterwards stock changes via inventory adjustments.
        if ($isCreate) {
            $rules['stock_quantity'] = ['nullable', 'integer', 'min:0', 'max:1000000'];
        }

        // Per-variant rules that depend on the variant's own id.
        foreach ((array) $this->input('variants', []) as $index => $variant) {
            $variantId = is_array($variant) ? ($variant['id'] ?? null) : null;

            $rules["variants.{$index}.sku"][] = Rule::unique('product_variants', 'sku')->ignore($variantId);

            if ($variantId !== null) {
                // Variants can only be updated through their own product.
                $rules["variants.{$index}.id"][] = Rule::exists('product_variants', 'id')->where('product_id', $product?->id ?? 0);
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'variants.*.name' => 'variant name',
            'variants.*.sku' => 'variant SKU',
            'variants.*.price' => 'variant price',
            'variants.*.stock_quantity' => 'variant stock',
        ];
    }

    /** Validated data mapped to model attributes (money in cents). */
    public function toModelAttributes(): array
    {
        $data = $this->validated();

        $data['price'] = Money::toCents($data['price']);
        $data['compare_at_price'] = isset($data['compare_at_price']) ? Money::toCents($data['compare_at_price']) : null;
        $data['is_featured'] = $this->boolean('is_featured');

        if (array_key_exists('variants', $data)) {
            $data['variants'] = array_map(function (array $variant) {
                $variant['price'] = isset($variant['price']) ? Money::toCents($variant['price']) : null;
                $variant['compare_at_price'] = isset($variant['compare_at_price']) ? Money::toCents($variant['compare_at_price']) : null;
                $variant['is_active'] = (bool) ($variant['is_active'] ?? true);
                $variant['options'] = $variant['options'] ?? null;

                return $variant;
            }, $data['variants']);
        }

        return $data;
    }
}
