<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Http\Resources\Admin\AdminOrderResource;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function sales(ReportRequest $request): JsonResponse
    {
        return $this->success([
            ...$this->range($request),
            ...$this->reports->sales($request->rangeStart(), $request->rangeEnd(), $request->grain()),
        ]);
    }

    public function orders(ReportRequest $request): JsonResponse
    {
        $page = $this->reports->orders($request->rangeStart(), $request->rangeEnd(), $request->validated(), $this->perPage());

        return $this->paginated($page, AdminOrderResource::class, null, [
            ...$this->range($request),
            'summary' => $this->reports->ordersSummary($request->rangeStart(), $request->rangeEnd()),
        ]);
    }

    public function products(ReportRequest $request): JsonResponse
    {
        $page = $this->reports->products($request->rangeStart(), $request->rangeEnd(), $request->validated(), $this->perPage());

        return $this->paginated($page, null, null, $this->range($request));
    }

    public function customers(ReportRequest $request): JsonResponse
    {
        $page = $this->reports->customers($request->rangeStart(), $request->rangeEnd(), $request->validated(), $this->perPage());

        return $this->paginated($page, null, null, $this->range($request));
    }

    public function inventory(ReportRequest $request): JsonResponse
    {
        $page = $this->reports->inventory($request->validated(), $this->perPage());

        return $this->paginated($page, null, null, [
            ...$this->range($request),
            'summary' => $this->reports->inventorySummary($request->rangeStart(), $request->rangeEnd()),
        ]);
    }

    private function range(ReportRequest $request): array
    {
        return [
            'from' => $request->rangeStart()->toDateString(),
            'to' => $request->rangeEnd()->toDateString(),
            'grain' => $request->grain(),
        ];
    }
}
