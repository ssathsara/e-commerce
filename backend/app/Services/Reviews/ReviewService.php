<?php

namespace App\Services\Reviews;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Business rules (config/shop.php -> reviews):
 *  - one review per customer per product (also enforced by a unique index);
 *  - require_purchase: the customer must have a DELIVERED order containing the product;
 *  - require_approval: new/edited reviews stay pending until a moderator approves them.
 * Product rating aggregates only count approved reviews.
 */
class ReviewService
{
    /**
     * @return array{can_review: bool, reason: ?string, review: ?Review}
     */
    public function eligibility(User $user, Product $product): array
    {
        $existing = Review::query()->where('user_id', $user->id)->where('product_id', $product->id)->first();

        if ($existing !== null) {
            return ['can_review' => false, 'reason' => 'You have already reviewed this product.', 'review' => $existing];
        }

        if (config('shop.reviews.require_purchase') && ! $this->hasPurchased($user, $product)) {
            return ['can_review' => false, 'reason' => 'Only customers who received this product can review it.', 'review' => null];
        }

        return ['can_review' => true, 'reason' => null, 'review' => null];
    }

    public function create(User $user, Product $product, array $data): Review
    {
        $eligibility = $this->eligibility($user, $product);

        if (! $eligibility['can_review']) {
            $status = $eligibility['review'] !== null ? 409 : 403;

            throw new BusinessRuleException($eligibility['reason'], $status);
        }

        return DB::transaction(function () use ($user, $product, $data) {
            $review = new Review($data);
            $review->product()->associate($product);
            $review->user()->associate($user);
            $review->status = $this->initialStatus();
            $review->save();

            $this->refreshProductRating($product->id);

            return $review;
        });
    }

    /** Editing a review sends it back to moderation when approval is required. */
    public function update(Review $review, array $data): Review
    {
        return DB::transaction(function () use ($review, $data) {
            $review->fill($data);
            $review->status = $this->initialStatus();
            $review->moderated_by = null;
            $review->moderated_at = null;
            $review->save();

            $this->refreshProductRating($review->product_id);

            return $review;
        });
    }

    public function moderate(Review $review, ReviewStatus $status, User $moderator): Review
    {
        return DB::transaction(function () use ($review, $status, $moderator) {
            $review->status = $status;
            $review->moderated_by = $moderator->id;
            $review->moderated_at = now();
            $review->save();

            $this->refreshProductRating($review->product_id);

            AuditLog::info('review.moderated', ['review_id' => $review->id, 'status' => $status->value]);

            return $review;
        });
    }

    public function delete(Review $review): void
    {
        DB::transaction(function () use ($review) {
            $productId = $review->product_id;
            $review->delete();
            $this->refreshProductRating($productId);
        });

        AuditLog::info('review.deleted', ['review_id' => $review->id]);
    }

    public function hasPurchased(User $user, Product $product): bool
    {
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', OrderStatus::Delivered))
            ->exists();
    }

    /** Recompute denormalised rating aggregates from approved reviews. */
    public function refreshProductRating(int $productId): void
    {
        $stats = Review::query()
            ->where('product_id', $productId)
            ->approved()
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as average')
            ->first();

        Product::withTrashed()->whereKey($productId)->update([
            'rating_count' => (int) $stats->review_count,
            'rating_average' => round((float) $stats->average, 2),
        ]);
    }

    private function initialStatus(): ReviewStatus
    {
        return config('shop.reviews.require_approval') ? ReviewStatus::Pending : ReviewStatus::Approved;
    }
}
