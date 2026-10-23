<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Envelope respons API yang konsisten (lihat docs/api/errors.md).
 * @2026-Alifiandra
 */
final class ApiResponse
{
    public static function requestId(?Request $request = null): string
    {
        $request ??= request();
        $id = $request->attributes->get('request_id');

        if (! $id) {
            $id = 'req_'.Str::lower((string) Str::ulid());
            $request->attributes->set('request_id', $id);
        }

        return $id;
    }

    public static function success(mixed $data, int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        $requestId = self::requestId();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => array_merge(['request_id' => $requestId], $meta),
        ], $status, array_merge(['X-Request-Id' => $requestId], $headers));
    }

    public static function error(string $code, string $message, int $status, mixed $details = null, array $headers = []): JsonResponse
    {
        $requestId = self::requestId();
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
            'meta' => ['request_id' => $requestId],
        ], $status, array_merge(['X-Request-Id' => $requestId], $headers));
    }
}
