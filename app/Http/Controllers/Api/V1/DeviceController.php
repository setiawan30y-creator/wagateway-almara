<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * REST API /api/v1/devices — kredensial tidak pernah ikut dikembalikan.
 * @2026-Alifiandra
 */
class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = Device::orderByDesc('is_default')->orderBy('id')->get()->map(fn (Device $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'provider' => $d->provider,
            'phone_number' => $d->phone_number,
            'status' => $d->status,
            'is_default' => $d->is_default,
            'is_active' => $d->is_active,
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
        ]);

        return ApiResponse::success($devices);
    }
}
