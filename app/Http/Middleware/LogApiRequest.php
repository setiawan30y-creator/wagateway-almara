<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Catat request API (metode, path, status, durasi) tanpa body/secret. @2026-Alifiandra */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            ApiLog::create([
                'api_key_id' => $request->attributes->get('api_key')?->id,
                'method' => $request->method(),
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - (float) $request->attributes->get('started_at', microtime(true))) * 1000),
                'ip' => $request->ip(),
                'request_id' => $response->headers->get('X-Request-Id') ?? ApiResponse::requestId($request),
            ]);
        } catch (Throwable) {
            // logging tidak boleh mengganggu respons
        }
    }
}
