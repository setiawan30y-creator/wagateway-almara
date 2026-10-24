<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmartLink;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** REST API /api/v1/smart-links @2026-Alifiandra */
class SmartLinkController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(SmartLink::with('device')->latest('id')->get()->map->toApiArray()->values());
    }
}
