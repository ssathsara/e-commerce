<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CouponType;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * Lookup data for admin forms and filters (enum options, category/brand pickers).
 */
class OptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $options = fn (array $cases) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);

        return $this->success([
            'product_statuses' => $options(ProductStatus::cases()),
            'order_statuses' => $options(OrderStatus::cases()),
            'payment_statuses' => $options(PaymentStatus::cases()),
            'review_statuses' => $options(ReviewStatus::cases()),
            'coupon_types' => $options(CouponType::cases()),
            'manual_movement_types' => $options(InventoryMovementType::manual()),
            'movement_types' => $options(InventoryMovementType::cases()),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id', 'is_active']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }
}
