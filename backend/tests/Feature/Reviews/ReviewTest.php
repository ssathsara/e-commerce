<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    private function deliveredPurchase(): array
    {
        $customer = $this->customer();
        $product = $this->product(1000, 10);
        $this->addToCart($customer, $product);
        $order = Order::find($this->checkout($customer)->json('data.id'));

        $admin = $this->admin();
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->signIn($admin)->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])->assertOk();
        }

        return [$customer, $product];
    }

    private function review(User $user, Product $product, int $rating = 5)
    {
        return $this->signIn($user)->postJson("/api/v1/products/{$product->slug}/reviews", [
            'rating' => $rating,
            'title' => 'Great',
            'body' => 'Really happy with this purchase.',
        ]);
    }

    public function test_only_customers_who_received_the_product_can_review(): void
    {
        $product = $this->product();
        $stranger = $this->customer();

        $this->signIn($stranger)->getJson("/api/v1/products/{$product->slug}/review-eligibility")->assertJsonPath('data.can_review', false);
        $this->review($stranger, $product)->assertForbidden();

        [$buyer, $bought] = $this->deliveredPurchase();
        $this->signIn($buyer)->getJson("/api/v1/products/{$bought->slug}/review-eligibility")->assertJsonPath('data.can_review', true);
        $this->review($buyer, $bought)->assertCreated()->assertJsonPath('data.status', 'pending');
    }

    public function test_duplicate_reviews_are_prevented(): void
    {
        [$buyer, $product] = $this->deliveredPurchase();

        $this->review($buyer, $product)->assertCreated();
        $this->review($buyer, $product)->assertStatus(409);
        $this->assertSame(1, Review::count());
    }

    public function test_review_validation(): void
    {
        [$buyer, $product] = $this->deliveredPurchase();

        $this->signIn($buyer)->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 6, 'body' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['rating', 'body']);
    }

    public function test_pending_reviews_are_not_public_until_approved_and_rating_is_recalculated(): void
    {
        [$buyer, $product] = $this->deliveredPurchase();
        $reviewId = $this->review($buyer, $product, 4)->json('data.id');

        $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonPath('meta.total', 0);

        $this->signIn($this->staff())->patchJson("/api/v1/admin/reviews/{$reviewId}/status", ['status' => 'approved'])->assertOk();

        $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonPath('meta.total', 1)->assertJsonPath('meta.distribution.4', 1);
        $this->assertEquals(4.0, (float) $product->fresh()->rating_average);
        $this->assertSame(1, $product->fresh()->rating_count);

        $this->signIn($this->admin())->patchJson("/api/v1/admin/reviews/{$reviewId}/status", ['status' => 'rejected'])->assertOk();
        $this->assertSame(0, $product->fresh()->rating_count);
    }

    public function test_moderation_permissions(): void
    {
        $review = Review::factory()->create();

        $this->signIn($this->customer())->patchJson("/api/v1/admin/reviews/{$review->id}/status", ['status' => 'approved'])->assertForbidden();
        $this->signIn($this->staff())->deleteJson("/api/v1/admin/reviews/{$review->id}")->assertForbidden();
        $this->signIn($this->admin())->deleteJson("/api/v1/admin/reviews/{$review->id}")->assertOk();
        $this->assertModelMissing($review);
    }

    public function test_customer_can_only_edit_own_review_and_editing_resubmits_for_approval(): void
    {
        $review = Review::factory()->status(ReviewStatus::Approved)->create();
        $owner = $review->user;

        $this->signIn($this->customer())->putJson("/api/v1/reviews/{$review->id}", ['rating' => 1, 'body' => 'Hijacked review text'])->assertForbidden();

        $this->signIn($owner)->putJson("/api/v1/reviews/{$review->id}", ['rating' => 3, 'body' => 'Updated after a month of use.'])
            ->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_admin_review_list_filters(): void
    {
        Review::factory()->count(2)->create();
        Review::factory()->status(ReviewStatus::Approved)->create();

        $this->signIn($this->admin())->getJson('/api/v1/admin/reviews?status=pending')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.pending_count', 2);
    }
}
