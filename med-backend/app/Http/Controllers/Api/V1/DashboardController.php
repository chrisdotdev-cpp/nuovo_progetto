<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Aggregati per le viste Panoramica dei tre ruoli. */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'role' => $request->user()->role,
            'data' => $this->dashboard->forUser($request->user()),
        ]);
    }
}
