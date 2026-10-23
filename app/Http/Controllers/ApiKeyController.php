<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Manajemen API key. Key penuh hanya ditampilkan sekali setelah dibuat.
 * @2026-Alifiandra
 */
class ApiKeyController extends Controller
{
    public function index()
    {
        return view('api-keys.index', [
            'keys' => ApiKey::with('user')->latest('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'label']);

        [$key, $plain] = ApiKey::generate($request->user(), $data['name']);
        AuditLog::record('api_key.create', 'Buat API key '.$key->name, ['prefix' => $key->prefix]);

        return redirect()->route('api-keys.index')
            ->with('success', 'API key dibuat. Salin sekarang — key tidak akan ditampilkan lagi.')
            ->with('plain_key', $plain);
    }

    public function destroy(ApiKey $apiKey)
    {
        if (! $apiKey->isRevoked()) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
            AuditLog::record('api_key.revoke', 'Cabut API key '.$apiKey->name, ['prefix' => $apiKey->prefix]);
        }

        return back()->with('success', 'API key dicabut.');
    }
}
