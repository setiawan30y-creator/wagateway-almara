<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Support\License;
use Illuminate\Http\Request;

/**
 * Halaman lisensi: paket aktif, pemakaian, aktivasi kode.
 * @2026-Alifiandra
 */
class LicenseController extends Controller
{
    public function show()
    {
        return view('license', [
            'license' => License::current(),
            'usage' => [
                'devices' => License::realDevices(),
                'messages_today' => License::messagesToday(),
            ],
            'plans' => config('license.plans'),
            'hasPublicKey' => License::publicKey() !== null,
        ]);
    }

    public function activate(Request $request)
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:4000']], [], ['key' => 'kode lisensi']);
        $key = preg_replace('/\s+/', '', $data['key']);
        $tenant = License::tenant();

        if ($error = License::applyKey($tenant, $key)) {
            return back()->withInput()->withErrors(['key' => $error]);
        }

        $lic = License::current();
        AuditLog::record('license.activate', 'Aktivasi lisensi '.($tenant->license_id ?? '').' paket '.$lic['label']);

        return redirect()->route('license.show')->with($lic['plan'] === 'free' ? 'warning' : 'success',
            $lic['plan'] === 'free' ? ($lic['warning'] ?? 'Lisensi tidak aktif.') : 'Lisensi aktif: paket '.$lic['label'].'. Terima kasih!');
    }

    /** Kembalikan ke paket Gratis (khusus super-admin). */
    public function remove(Request $request)
    {
        abort_unless($request->user()->is_superadmin, 403);

        License::tenant()->forceFill(['plan' => 'free', 'max_devices' => null, 'expires_at' => null, 'license_id' => null])->save();
        License::flush();
        AuditLog::record('license.remove', 'Paket dikembalikan ke Gratis');

        return redirect()->route('license.show')->with('success', 'Paket dikembalikan ke Gratis.');
    }
}
