<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi REST API memakai API key.
 * Header: Authorization: Bearer alm_xxx  atau  X-Api-Key: alm_xxx
 * @2026-Alifiandra
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (! $plain || ! is_string($plain)) {
            return ApiResponse::error('UNAUTHENTICATED', 'API key wajib dikirim melalui header Authorization: Bearer atau X-Api-Key.', 401);
        }

        $apiKey = ApiKey::findActiveByPlain($plain);

        if (! $apiKey) {
            return ApiResponse::error('UNAUTHENTICATED', 'API key tidak valid atau sudah dicabut.', 401);
        }

        // Catat penggunaan terakhir maksimal sekali per menit agar tidak membebani DB.
        if (! $apiKey->last_used_at || $apiKey->last_used_at->lt(now()->subMinute())) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        \App\Support\Tenancy::set($apiKey->tenant_id);

        if (! \App\Support\Tenancy::current()?->isActive()) {
            return \App\Support\ApiResponse::error('TENANT_SUSPENDED', 'Akun usaha ini sedang ditangguhkan. Hubungi penyedia aplikasi.', 403);
        }

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
