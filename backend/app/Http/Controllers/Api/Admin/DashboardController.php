<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->success($this->dashboard->summary($request->user()));
    }

    public function notifications(Request $request): JsonResponse
    {
        return $this->success($this->dashboard->notifications($request->user()));
    }
}
