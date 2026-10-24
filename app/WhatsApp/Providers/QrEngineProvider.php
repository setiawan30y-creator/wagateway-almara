<?php

namespace App\WhatsApp\Providers;

use App\Models\Device;
use App\Support\MediaFile;
use App\WhatsApp\ConnectionStatus;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter ke engine QR Multi-Device (WhatsApp Web).
 * Default memakai engine bawaan di folder engine/ (WA_ENGINE_URL & WA_ENGINE_TOKEN di .env),
 * tetapi Base URL / token / path bisa diganti per perangkat untuk engine lain yang kompatibel.
 *
 * Kontrak HTTP engine:
 *   POST {base}/sessions/start  {"session","webhook_url","webhook_token"}
 *   GET  {base}/status?session= -> {"connected": bool, "message", "phone"}
 *   GET  {base}/qr?session=     -> {"qr": "data:image/png;base64,..." | teks QR, "connected"}
 *   POST {base}/send-message    {"session","to","message"} -> {"success", "id"}
 *   POST {base}/send-media      {"session","to","type","mime","name","caption","data"(base64)}
 *   POST {base}/sessions/logout {"session"}
 * Event engine -> gateway: POST {APP_URL}/api/webhooks/qr/{device_id} (header X-Webhook-Token)
 *
 * @2026-Alifiandra
 */
class QrEngineProvider implements WhatsAppProvider
{
    public function sendText(Device $device, string $to, string $body): SendResult
    {
        try {
            $response = $this->client($device)->post($device->credential('send_path') ?: '/send-message', [
                'session' => $this->session($device),
                'to' => $to,
                'message' => $body,
            ]);
        } catch (ConnectionException $e) {
            return SendResult::failure('Engine QR tidak dapat dihubungi. Pastikan engine berjalan (npm start di folder engine).', retryable: true);
        }

        if ($response->successful() && $response->json('success', true) !== false) {
            $id = $response->json('id') ?? $response->json('data.id') ?? $response->json('key.id');

            return SendResult::success($id ? (string) $id : null);
        }

        $error = $response->json('message') ?? $response->json('error') ?? ('HTTP '.$response->status());

        return SendResult::failure('Engine QR: '.(is_string($error) ? $error : json_encode($error)), retryable: $response->serverError());
    }

    public function sendMedia(Device $device, string $to, MediaFile $media, ?string $caption = null): SendResult
    {
        try {
            $response = $this->client($device)->timeout(120)->post('/send-media', [
                'session' => $this->session($device),
                'to' => $to,
                'type' => $media->type,
                'mime' => $media->mime,
                'name' => $media->name,
                'caption' => $caption,
                'data' => $media->base64(),
            ]);
        } catch (ConnectionException $e) {
            return SendResult::failure('Engine QR tidak dapat dihubungi. Pastikan engine berjalan (npm start di folder engine).', retryable: true);
        }

        if ($response->successful() && $response->json('success', true) !== false) {
            return SendResult::success($response->json('id') ? (string) $response->json('id') : null);
        }

        return SendResult::failure('Engine QR: '.($response->json('message') ?? 'HTTP '.$response->status()), $response->serverError());
    }

    public function status(Device $device): ConnectionStatus
    {
        try {
            $response = $this->client($device)->get($device->credential('status_path') ?: '/status', ['session' => $this->session($device)]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Engine QR tidak dapat dihubungi. Jalankan engine terlebih dahulu.');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Engine QR: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        $connected = (bool) ($response->json('connected') ?? $response->json('data.connected') ?? false);

        if ($connected && ($phone = $response->json('phone')) && $device->phone_number !== $phone) {
            $device->forceFill(['phone_number' => $phone])->save();
        }

        return new ConnectionStatus($connected, (string) ($response->json('message') ?? ($connected ? 'Terhubung' : 'Belum terhubung — scan QR.')));
    }

    /** Mulai sesi di engine dan daftarkan URL webhook pesan masuk. */
    public function startSession(Device $device, string $webhookUrl): ConnectionStatus
    {
        try {
            $response = $this->client($device)->post('/sessions/start', [
                'session' => $this->session($device),
                'webhook_url' => $webhookUrl,
                'webhook_token' => (string) $device->credential('webhook_token'),
            ]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Engine QR tidak dapat dihubungi di '.$this->baseUrl($device).'. Jalankan: cd engine; npm start');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Engine QR: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return new ConnectionStatus((bool) $response->json('connected'), (string) $response->json('message'));
    }

    /** Ambil QR pairing dari engine (data URI gambar atau teks QR mentah). */
    public function pairingQr(Device $device): ConnectionStatus
    {
        try {
            $response = $this->client($device)->get($device->credential('qr_path') ?: '/qr', ['session' => $this->session($device)]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Engine QR tidak dapat dihubungi. Jalankan: cd engine; npm start');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Engine QR: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        if ($response->json('connected')) {
            return new ConnectionStatus(true, (string) $response->json('message', 'Terhubung'));
        }

        $qr = $response->json('qr') ?? $response->json('data.qr');

        return new ConnectionStatus(false, $qr ? 'Scan QR dengan WhatsApp > Perangkat tertaut > Tautkan perangkat.' : (string) ($response->json('message') ?? 'QR belum tersedia, tunggu sebentar…'), $qr);
    }

    public function logout(Device $device): bool
    {
        try {
            return $this->client($device)->post('/sessions/logout', ['session' => $this->session($device)])->successful();
        } catch (ConnectionException $e) {
            return false;
        }
    }

    public static function credentialFields(): array
    {
        return [
            'base_url' => ['label' => 'Base URL Engine', 'type' => 'url', 'required' => false, 'placeholder' => 'Kosongkan = engine bawaan ('.config('whatsapp.engine.url').')'],
            'api_token' => ['label' => 'API Token Engine', 'type' => 'password', 'required' => false, 'placeholder' => 'Kosongkan = WA_ENGINE_TOKEN di .env'],
            'session' => ['label' => 'Nama Sesi', 'type' => 'text', 'required' => false, 'placeholder' => 'Otomatis'],
            'send_path' => ['label' => 'Path Kirim', 'type' => 'text', 'required' => false, 'placeholder' => '/send-message'],
            'status_path' => ['label' => 'Path Status', 'type' => 'text', 'required' => false, 'placeholder' => '/status'],
            'qr_path' => ['label' => 'Path QR', 'type' => 'text', 'required' => false, 'placeholder' => '/qr'],
            'webhook_token' => ['label' => 'Token Webhook Masuk', 'type' => 'password', 'required' => false, 'placeholder' => 'Otomatis dibuat'],
        ];
    }

    public function session(Device $device): string
    {
        return (string) ($device->credential('session') ?: 'device-'.$device->id);
    }

    private function baseUrl(Device $device): string
    {
        return rtrim((string) ($device->credential('base_url') ?: config('whatsapp.engine.url')), '/');
    }

    protected function client(Device $device): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl($device))
            ->acceptJson()
            ->timeout(config('whatsapp.http_timeout'));

        if ($token = ($device->credential('api_token') ?: config('whatsapp.engine.token'))) {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
