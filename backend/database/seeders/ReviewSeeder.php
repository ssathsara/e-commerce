<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Database\Seeder;

/**
 * Reviews are only created for products customers actually received (matching the
 * "require purchase" business rule). Most are approved, some await moderation.
 */
class ReviewSeeder extends Seeder
{
    private const SAMPLES = [
        5 => ['Absolutely love it', 'Exceeded my expectations in every way. Great quality and it arrived quickly.'],
        4 => ['Very good', 'Really solid product. Minor nitpicks, but I would happily buy it again.'],
        3 => ['Decent for the price', 'Does the job. Build quality could be a little better, but overall fine.'],
        2 => ['Not quite right for me', 'It works, but it did not match the description as closely as I hoped.'],
    ];

    public function run(ReviewService $reviews): void
    {
        $delivered = Order::query()->where('status', OrderStatus::Delivered)->with('items')->get();
        $productIds = [];

        foreach ($delivered as $order) {
            foreach ($order->items as $item) {
                if ($item->product_id === null || random_int(1, 10) > 7) {
                    continue;
                }

                $exists = Review::query()->where('user_id', $order->user_id)->where('product_id', $item->product_id)->exists();
                if ($exists) {
                    continue;
                }

                $rating = collect([5, 5, 5, 4, 4, 4, 3, 2])->random();
                [$title, $body] = self::SAMPLES[min($rating, 5)] ?? self::SAMPLES[4];

                $review = new Review(['rating' => $rating, 'title' => $title, 'body' => $body]);
                $review->product_id = $item->product_id;
                $review->user_id = $order->user_id;
                $review->status = random_int(1, 6) === 1 ? ReviewStatus::Pending : ReviewStatus::Approved;
                $review->created_at = $order->created_at->copy()->addDays(random_int(3, 10))->min(now());
                $review->save();

                $productIds[$item->product_id] = true;
            }
        }

        foreach (array_keys($productIds) as $productId) {
            $reviews->refreshProductRating($productId);
        }
    }
}
