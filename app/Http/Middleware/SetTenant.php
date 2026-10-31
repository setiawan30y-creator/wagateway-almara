<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

/**
 * Pasang konteks penyewa dari pengguna yang login (termasuk mode "masuk ke akun" super-admin).
 * Dijalankan sebelum route model binding agar data penyewa lain tidak bisa dibuka.
 * @2026-Alifiandra
 */
class SetTenant
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            Tenancy::setFromUser($user);
        }

        return $next($request);
    }
}
