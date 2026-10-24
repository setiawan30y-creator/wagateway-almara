<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Analytics;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** REST API /api/v1/analytics @2026-Alifiandra */
class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:90']]);

        return ApiResponse::success(Analytics::summary((int) ($data['days'] ?? 7)));
    }
}
