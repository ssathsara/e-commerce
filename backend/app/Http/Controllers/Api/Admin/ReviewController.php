<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewModerationRequest;
use App\Http\Resources\Admin\AdminReviewResource;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $search = $request->query('search');
        $like = $search ? '%'.addcslashes($search, '%_\\').'%' : null;

        $reviews = Review::query()
            ->with(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name'])
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('rating'), fn ($q, $v) => $q->where('rating', $v))
            ->when($request->query('product_id'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($like, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('body', 'like', $like)
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $like))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like))))
            ->latest('id')
            ->paginate($this->perPage());

        return $this->paginated($reviews, AdminReviewResource::class, null, [
            'pending_count' => Review::query()->where('status', ReviewStatus::Pending)->count(),
        ]);
    }

    public function show(Review $review): JsonResponse
    {
        return $this->success(new AdminReviewResource($review->load(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name'])));
    }

    public function moderate(ReviewModerationRequest $request, Review $review): JsonResponse
    {
        $status = ReviewStatus::from($request->validated('status'));
        $this->reviews->moderate($review, $status, $request->user());

        return $this->success(
            new AdminReviewResource($review->load(['product:id,name,slug', 'user:id,name,email', 'moderator:id,name'])),
            "Review {$status->value}.",
        );
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->reviews->delete($review);

        return $this->message('Review deleted.');
    }
}
