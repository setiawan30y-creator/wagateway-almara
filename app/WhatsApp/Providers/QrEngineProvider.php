<?php

namespace App\WhatsApp\Providers;

use App\Models\Device;
use App\WhatsApp\ConnectionStatus;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter generik ke engine QR Multi-Device eksternal (layanan terpisah, sebaiknya di jaringan privat).
 *
 * Kontrak HTTP yang diharapkan dari engine (path dapat diubah per perangkat):
 *   POST {base_url}{send_path}   body: {"session": "...", "to": "628...", "message": "..."}
 *        -> 2xx JSON, id pesan dibaca dari "id" / "data.id" / "key.id"
 *   GET  {base_url}{status_path}?session=...  -> {"connected": true|false, "message": "..."}
 *   GET  {base_url}{qr_path}?session=...      -> {"qr": "<string QR atau data:image/png;base64,...>"}
 * Header: Authorization: Bearer {api_token} (bila diisi)
 *
 * @2026-Alifiandra
 */
class QrEngineProvider implements WhatsAppProvider
{
    public function sendText(Device $device, string $to, string $body): SendResult
    {
        if (! $device->credential('base_url')) {
            return SendResult::failure('Base URL engine QR belum diisi.');
        }

        try {
            $response = $this->client($device)->post($device->credential('send_path') ?: '/send-message', [
                'session' => $device->credential('session') ?: 'default',
                'to' => $to,
                'message' => $body,
            ]);
        } catch (ConnectionException $e) {
            return SendResult::failure('Engine QR tidak dapat dihubungi.', retryable: true);
        }

        if ($response->successful() && $response->json('success', true) !== false) {
            $id = $response->json('id') ?? $response->json('data.id') ?? $response->json('key.id');

            return SendResult::success($id ? (string) $id : null);
        }

        $error = $response->json('message') ?? $response->json('error') ?? ('HTTP '.$response->status());

        return SendResult::failure('Engine QR: '.(is_string($error) ? $error : json_encode($error)), retryable: $response->serverError());
    }

    public function status(Device $device): ConnectionStatus
    {
        if (! $device->credential('base_url')) {
            return new ConnectionStatus(false, 'Base URL engine QR belum diisi.');
        }

        try {
            $response = $this->client($device)->get($device->credential('status_path') ?: '/status', [
                'session' => $device->credential('session') ?: 'default',
            ]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Engine QR tidak dapat dihubungi.');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Engine QR: HTTP '.$response->status());
        }

        $connected = (bool) ($response->json('connected') ?? $response->json('data.connected') ?? false);

        return new ConnectionStatus($connected, (string) ($response->json('message') ?? ($connected ? 'Terhubung' : 'Belum terhubung — scan QR.')));
    }

    /** Ambil QR pairing dari engine (string QR mentah atau data URI gambar). */
    public function pairingQr(Device $device): ConnectionStatus
    {
        try {
            $response = $this->client($device)->get($device->credential('qr_path') ?: '/qr', [
                'session' => $device->credential('session') ?: 'default',
            ]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Engine QR tidak dapat dihubungi.');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Engine QR: HTTP '.$response->status());
        }

        $qr = $response->json('qr') ?? $response->json('data.qr');

        return new ConnectionStatus(false, $qr ? 'Scan QR dengan WhatsApp > Perangkat tertaut.' : (string) ($response->json('message') ?? 'QR belum tersedia.'), $qr);
    }

    public static function credentialFields(): array
    {
        return [
            'base_url' => ['label' => 'Base URL Engine', 'type' => 'url', 'required' => true, 'placeholder' => 'http://127.0.0.1:3000'],
            'api_token' => ['label' => 'API Token Engine', 'type' => 'password', 'required' => false, 'placeholder' => 'Opsional'],
            'session' => ['label' => 'Nama Sesi', 'type' => 'text', 'required' => false, 'placeholder' => 'default'],
            'send_path' => ['label' => 'Path Kirim', 'type' => 'text', 'required' => false, 'placeholder' => '/send-message'],
            'status_path' => ['label' => 'Path Status', 'type' => 'text', 'required' => false, 'placeholder' => '/status'],
            'qr_path' => ['label' => 'Path QR', 'type' => 'text', 'required' => false, 'placeholder' => '/qr'],
        ];
    }

    protected function client(Device $device): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) $device->credential('base_url'), '/'))
            ->acceptJson()
            ->timeout(config('whatsapp.http_timeout'));

        if ($token = $device->credential('api_token')) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
