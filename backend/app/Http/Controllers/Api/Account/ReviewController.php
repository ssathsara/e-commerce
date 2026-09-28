<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    /** The signed-in customer's own reviews (any status). */
    public function index(Request $request): JsonResponse
    {
        $reviews = $request->user()->reviews()
            ->with('product:id,name,slug')
            ->latest()
            ->paginate($this->perPage(10));

        return $this->paginated($reviews, ReviewResource::class);
    }

    public function eligibility(Request $request, string $slug): JsonResponse
    {
        $product = Product::query()->visible()->where('slug', $slug)->firstOrFail();
        $result = $this->reviews->eligibility($request->user(), $product);

        return $this->success([
            'can_review' => $result['can_review'],
            'reason' => $result['reason'],
            'review' => $result['review'] ? (new ReviewResource($result['review']))->resolve($request) : null,
        ]);
    }

    public function store(ReviewRequest $request, string $slug): JsonResponse
    {
        $product = Product::query()->visible()->where('slug', $slug)->firstOrFail();
        $review = $this->reviews->create($request->user(), $product, $request->validated());

        $message = config('shop.reviews.require_approval')
            ? 'Thanks! Your review will appear once it has been approved.'
            : 'Thanks for your review!';

        return $this->created(new ReviewResource($review), $message);
    }

    public function update(ReviewRequest $request, Review $review): JsonResponse
    {
        Gate::authorize('update', $review);

        $review = $this->reviews->update($review, $request->validated());

        return $this->success(new ReviewResource($review), 'Review updated.');
    }

    public function destroy(Review $review): JsonResponse
    {
        Gate::authorize('delete', $review);

        $this->reviews->delete($review);

        return $this->message('Review deleted.');
    }
}
