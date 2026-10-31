<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Message;
use App\Services\InboundService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

/**
 * Webhook Meta Cloud API: verifikasi, pesan masuk, dan pembaruan status pesan.
 * URL: {APP_URL}/api/webhooks/meta
 * @2026-Alifiandra
 */
class MetaWebhookController extends Controller
{
    private const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    public function verify(Request $request)
    {
        $token = config('whatsapp.meta.webhook_verify_token');

        if ($token && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, InboundService $inbound, WebhookDispatcher $webhooks)
    {
        $secret = config('whatsapp.meta.app_secret');

        if ($secret) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

            if (! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
                return response('Invalid signature', 401);
            }
        }

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->applyStatus((array) $status, $webhooks);
                }

                $messages = (array) ($value['messages'] ?? []);

                if ($messages) {
                    $device = $this->resolveDevice((string) data_get($value, 'metadata.phone_number_id'));
                    $names = collect((array) ($value['contacts'] ?? []))->mapWithKeys(fn ($c) => [(string) data_get($c, 'wa_id') => data_get($c, 'profile.name')]);

                    if ($device) {
                        \App\Support\Tenancy::set($device->tenant_id);
                    }

                    foreach ($messages as $msg) {
                        if (! $device) {
                            break;
                        }

                        $from = (string) data_get($msg, 'from');
                        $media = $this->fetchMedia($device, (array) $msg);
                        $text = $media ? (string) data_get($msg, ($msg['type'] ?? '').'.caption', '') : $this->extractText((array) $msg);
                        $inbound->receive($device, $from, $text, data_get($msg, 'id'), $names[$from] ?? null, $media);
                    }
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function resolveDevice(string $phoneNumberId): ?Device
    {
        return Device::where('provider', 'meta')->where('is_active', true)->get()
            ->first(fn (Device $d) => (string) $d->credential('phone_number_id') === $phoneNumberId)
            ?? Device::where('provider', 'meta')->where('is_active', true)->orderByDesc('is_default')->first();
    }

    /** Unduh media masuk (gambar/video/audio/dokumen/stiker) dari Meta bila ada. */
    private function fetchMedia(Device $device, array $msg): ?\App\Support\MediaFile
    {
        $type = $msg['type'] ?? null;

        if (! in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) || ! ($id = data_get($msg, $type.'.id'))) {
            return null;
        }

        $provider = app(\App\WhatsApp\ProviderManager::class)->for($device);
        $file = $provider instanceof \App\WhatsApp\Providers\MetaCloudProvider ? $provider->downloadMedia($device, (string) $id) : null;

        return $file ? app(\App\Services\MediaStorage::class)->storeBinary($file[0], $file[1] ?: (string) data_get($msg, $type.'.mime_type', 'application/octet-stream'), data_get($msg, 'document.filename')) : null;
    }

    private function extractText(array $msg): string
    {
        return match ($msg['type'] ?? 'text') {
            'text' => (string) data_get($msg, 'text.body', ''),
            'button' => (string) data_get($msg, 'button.text', ''),
            'interactive' => (string) (data_get($msg, 'interactive.button_reply.title') ?? data_get($msg, 'interactive.list_reply.title') ?? '[interactive]'),
            'image', 'video', 'document', 'audio', 'sticker' => '['.$msg['type'].'] '.(string) data_get($msg, $msg['type'].'.caption', ''),
            'location' => '[lokasi] '.data_get($msg, 'location.latitude').','.data_get($msg, 'location.longitude'),
            default => '['.($msg['type'] ?? 'unknown').']',
        };
    }

    private function applyStatus(array $status, WebhookDispatcher $webhooks): void
    {
        $id = $status['id'] ?? null;
        $new = $status['status'] ?? null;

        if (! $id || ! isset(self::RANK[$new])) {
            return;
        }

        $message = Message::where('provider_message_id', $id)->where('direction', 'out')->first();

        // Abaikan callback duplikat / out-of-order (status tidak boleh mundur).
        if (! $message || self::RANK[$new] <= (self::RANK[$message->status] ?? 0)) {
            return;
        }

        $message->status = $new;

        if ($new === 'failed') {
            $message->error = 'Meta: '.(data_get($status, 'errors.0.title') ?? 'pengiriman gagal');
        }

        $message->save();
        $webhooks->dispatch('message.status', ['message' => $message->toApiArray()]);
    }
}
