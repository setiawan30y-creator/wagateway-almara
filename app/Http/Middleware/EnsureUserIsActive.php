<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Keluarkan pengguna yang dinonaktifkan. @2026-Alifiandra */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = \App\Support\Tenancy::current();

        // Penyewa ditangguhkan: pengguna biasa tidak bisa masuk (super-admin tetap bisa)
        if ($request->user() && $tenant && ! $tenant->isActive() && ! $request->user()->is_superadmin) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Akun usaha Anda sedang ditangguhkan. Hubungi penyedia aplikasi.']);
        }

        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan.']);
        }

        return $next($request);
    }
}
