<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Setting;
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
        $payload = License::verify($key);

        if (! $payload) {
            return back()->withInput()->withErrors(['key' => License::publicKey() ? 'Kode lisensi tidak valid. Periksa kembali (salin utuh tanpa terpotong).' : 'Aplikasi belum memiliki kunci publik lisensi (config/license-public.pem). Hubungi penyedia aplikasi.']);
        }

        Setting::set('license_key', $key);
        License::flush();
        $lic = License::current();
        AuditLog::record('license.activate', 'Aktivasi lisensi '.($payload['id'] ?? '').' paket '.$lic['label']);

        return redirect()->route('license.show')->with($lic['plan'] === 'free' ? 'warning' : 'success',
            $lic['plan'] === 'free' ? ($lic['warning'] ?? 'Lisensi tidak aktif.') : 'Lisensi aktif: paket '.$lic['label'].'. Terima kasih!');
    }

    public function remove()
    {
        Setting::set('license_key', '');
        License::flush();
        AuditLog::record('license.remove', 'Lisensi dihapus — kembali ke paket Gratis');

        return redirect()->route('license.show')->with('success', 'Lisensi dihapus. Aplikasi kembali ke paket Gratis.');
    }
}
