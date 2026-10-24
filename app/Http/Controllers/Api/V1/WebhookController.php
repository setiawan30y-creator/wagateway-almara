<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** REST API /api/v1/webhooks @2026-Alifiandra */
class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Webhook::orderBy('id')->get()->map->toApiArray()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500', 'starts_with:http://,https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(array_keys(Webhook::EVENTS))],
        ]);

        $secret = 'whsec_'.Str::random(32);
        $webhook = Webhook::create($data + ['secret' => $secret, 'is_active' => true]);

        // Secret hanya dikembalikan sekali saat pembuatan.
        return ApiResponse::success($webhook->toApiArray() + ['secret' => $secret], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $webhook = Webhook::find($id);

        if (! $webhook) {
            return ApiResponse::error('NOT_FOUND', 'Webhook tidak ditemukan.', 404);
        }

        $webhook->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
