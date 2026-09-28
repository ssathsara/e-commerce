<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base controller providing the uniform API envelope:
 *
 *   { "success": true, "message": "...", "data": ..., "meta": {...} }
 */
abstract class Controller
{
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => null], $status);
    }

    /**
     * @param  class-string<JsonResource>|null  $resource  Null when items are already plain arrays (e.g. reports).
     */
    protected function paginated(LengthAwarePaginator $paginator, ?string $resource = null, ?string $message = null, array $extraMeta = []): JsonResponse
    {
        $items = $resource !== null
            ? $resource::collection($paginator->getCollection())->resolve(request())
            : $paginator->getCollection()->values()->all();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ], $extraMeta),
        ]);
    }

    /** Bounded per_page from the query string. */
    protected function perPage(?int $default = null): int
    {
        $default ??= (int) config('shop.pagination.default_per_page');
        $max = (int) config('shop.pagination.max_per_page');

        return max(1, min($max, (int) request()->integer('per_page', $default)));
    }
}
