<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * Customer-side review ownership. Moderation endpoints are protected by
 * the "approve reviews" / "delete reviews" permissions on the admin routes.
 */
class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }
}
