<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\WhatsApp\ProviderManager;
use App\WhatsApp\Providers\QrEngineProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manajemen perangkat / nomor WhatsApp.
 * @2026-Alifiandra
 */
class DeviceController extends Controller
{
    public function __construct(private ProviderManager $providers) {}

    public function index()
    {
        return view('devices.index', [
            'devices' => Device::withCount('messages')->orderByDesc('is_default')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $provider = (string) $request->query('provider', 'log');
        $provider = array_key_exists($provider, config('whatsapp.providers')) ? $provider : 'log';

        return view('devices.form', [
            'device' => new Device(['provider' => $provider, 'is_active' => true]),
            'providers' => $this->providers->options(),
            'fieldsByProvider' => $this->allFields(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $device = Device::create($data);

        if ($device->is_default || Device::count() === 1) {
            $device->makeDefault();
        }

        AuditLog::record('device.create', 'Tambah perangkat '.$device->name, ['provider' => $device->provider]);

        return redirect()->route('devices.index')->with('success', 'Perangkat ditambahkan.');
    }

    public function edit(Device $device)
    {
        return view('devices.form', [
            'device' => $device,
            'providers' => $this->providers->options(),
            'fieldsByProvider' => $this->allFields(),
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $data = $this->validated($request, $device);
        $device->update($data);

        if ($device->is_default) {
            $device->makeDefault();
        }

        AuditLog::record('device.update', 'Ubah perangkat '.$device->name);

        return redirect()->route('devices.index')->with('success', 'Perangkat diperbarui.');
    }

    public function destroy(Device $device)
    {
        AuditLog::record('device.delete', 'Hapus perangkat '.$device->name);
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Perangkat dihapus. Riwayat pesan tetap tersimpan.');
    }

    public function makeDefault(Device $device)
    {
        $device->makeDefault();

        return back()->with('success', $device->name.' dijadikan perangkat utama.');
    }

    public function check(Device $device)
    {
        $status = $this->providers->for($device)->status($device);

        $device->forceFill([
            'status' => $status->connected ? 'connected' : 'disconnected',
            'last_seen_at' => $status->connected ? now() : $device->last_seen_at,
        ])->save();

        return back()->with($status->connected ? 'success' : 'warning', $device->name.': '.$status->message);
    }

    public function qr(Device $device)
    {
        abort_unless($device->provider === 'qr', 404);

        $provider = $this->providers->for($device);
        $status = $provider->status($device);

        if ($status->connected) {
            $device->forceFill(['status' => 'connected', 'last_seen_at' => now()])->save();

            return response()->json(['connected' => true, 'message' => $status->message, 'qr' => null]);
        }

        /** @var QrEngineProvider $provider */
        $pairing = $provider->pairingQr($device);

        return response()->json(['connected' => false, 'message' => $pairing->message, 'qr' => $pairing->qr]);
    }

    public function pair(Device $device)
    {
        abort_unless($device->provider === 'qr', 404);

        return view('devices.pair', compact('device'));
    }

    private function validated(Request $request, ?Device $device = null): array
    {
        $provider = (string) $request->input('provider');
        $fields = array_key_exists($provider, config('whatsapp.providers')) ? $this->providers->credentialFields($provider) : [];

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', Rule::in(array_keys(config('whatsapp.providers')))],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];

        foreach ($fields as $key => $field) {
            $isSecret = $field['type'] === 'password';
            // Field rahasia boleh dikosongkan saat edit = tetap memakai nilai lama.
            $required = $field['required'] && ! ($isSecret && $device?->credential($key) && $device->provider === $provider);
            $rules["credentials.$key"] = array_filter([
                $required ? 'required' : 'nullable',
                'string',
                'max:2000',
                $field['type'] === 'url' ? 'url' : null,
            ]);
        }

        $data = $request->validate($rules, [], [
            'name' => 'nama perangkat',
            ...collect($fields)->mapWithKeys(fn ($f, $k) => ["credentials.$k" => $f['label']])->all(),
        ]);

        $credentials = [];

        foreach ($fields as $key => $field) {
            $value = $data['credentials'][$key] ?? null;

            if (($value === null || $value === '') && $field['type'] === 'password' && $device && $device->provider === $provider) {
                $value = $device->credential($key);
            }

            if ($value !== null && $value !== '') {
                $credentials[$key] = $value;
            }
        }

        return [
            'name' => $data['name'],
            'provider' => $provider,
            'phone_number' => $data['phone_number'] ?? null,
            'credentials' => $credentials,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
            'status' => $device && $device->provider === $provider ? $device->status : 'unknown',
        ];
    }

    private function allFields(): array
    {
        return collect(array_keys(config('whatsapp.providers')))
            ->mapWithKeys(fn ($p) => [$p => $this->providers->credentialFields($p)])
            ->all();
    }
}
