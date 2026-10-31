<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Pendaftaran mandiri akun usaha baru (paket Gratis). Aktif bila SIGNUP_ENABLED=true di .env.
 * @2026-Alifiandra
 */
class SignupController extends Controller
{
    public function show()
    {
        abort_unless(config('license.signup_enabled'), 404);

        return view('auth.signup');
    }

    public function store(Request $request)
    {
        abort_unless(config('license.signup_enabled'), 404);

        $key = 'signup|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429, 'Terlalu banyak pendaftaran. Coba lagi nanti.');

        Tenancy::forget();
        $data = $request->validate([
            'business' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [], ['business' => 'nama usaha', 'name' => 'nama Anda', 'phone' => 'nomor WhatsApp']);

        $phone = PhoneNumber::normalize($data['phone']);
        if (! PhoneNumber::isValid($phone)) {
            return back()->withInput()->withErrors(['phone' => 'Nomor WhatsApp tidak valid.']);
        }

        RateLimiter::hit($key, 3600);

        $user = DB::transaction(function () use ($data, $phone) {
            $tenant = Tenant::create(['name' => $data['business'], 'plan' => 'free', 'contact_name' => $data['name'], 'contact_phone' => $phone]);

            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            $user->forceFill(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_active' => true])->save();

            Tenancy::run($tenant, fn () => Device::create(['name' => 'Simulasi', 'provider' => 'log', 'is_active' => true, 'is_default' => true, 'credentials' => []]));

            // kabari pengelola platform
            Tenancy::run(Tenant::defaultId(), fn () => AppNotification::notify('tenant.signup', 'Pendaftar baru: '.$tenant->name,
                $data['name'].' · +'.$phone.' · '.$data['email'], route('admin.tenants.show', $tenant), 'admins'));

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        Tenancy::setFromUser($user);
        AuditLog::record('auth.signup', 'Pendaftaran akun usaha baru');

        return redirect()->route('dashboard')->with('success', 'Selamat datang! Akun usaha Anda aktif dengan paket Gratis. Hubungkan nomor WhatsApp di menu Perangkat.');
    }
}
