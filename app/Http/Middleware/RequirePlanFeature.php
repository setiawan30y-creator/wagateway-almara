<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\License;
use Closure;
use Illuminate\Http\Request;

/**
 * Fitur khusus paket berbayar (mis. broadcast, ai). Pemakaian: ->middleware('feature:broadcast')
 * @2026-Alifiandra
 */
class RequirePlanFeature
{
    public const NAMES = ['broadcast' => 'Broadcast', 'ai' => 'AI Agent'];

    public function handle(Request $request, Closure $next, string $feature)
    {
        if (License::allows($feature)) {
            return $next($request);
        }

        $name = self::NAMES[$feature] ?? $feature;
        $message = "Fitur {$name} hanya tersedia di paket Pro. Paket Anda saat ini: ".License::current()['label'].'.';

        if ($request->is('api/*')) {
            return ApiResponse::error('PLAN_REQUIRED', $message, 403);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'text' => $message, 'message' => $message], 403);
        }

        return redirect()->route('license.show')->with('warning', $message);
    }
}
