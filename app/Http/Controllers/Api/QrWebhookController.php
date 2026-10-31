<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Device;
use App\Models\Message;
use App\Services\InboundService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Event dari engine QR Multi-Device.
 * POST {APP_URL}/api/webhooks/qr/{device}  header X-Webhook-Token
 *   {"type":"message","from":"628…","message":"…","id":"…","name":"…"}   pesan masuk
 *   {"type":"status","id":"…","status":"delivered|read|failed"}          status pesan keluar
 *   {"type":"session","status":"connected|reconnecting|logged_out","phone":"628…"}
 *   {"type":"message", …, "group":{"jid":"…@g.us","subject":"…"}}       pesan masuk dari grup (from = pengirim)
 *   {"type":"contacts","contacts":[{"phone":"628…","name":"…"}]}           sinkron kontak HP (maks 1000 / request)
 *   {"type":"history","messages":[{id, from, from_me, message, timestamp, name, group?}]}  riwayat chat
 *   {"type":"groups","groups":[{jid, subject, desc, size, is_admin, announce}]}             daftar grup
 * @2026-Alifiandra
 */
class QrWebhookController extends Controller
{
    private const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    public function __invoke(Request $request, Device $device, InboundService $inbound, WebhookDispatcher $webhooks)
    {
        $token = (string) $device->credential('webhook_token');
        $given = (string) ($request->header('X-Webhook-Token') ?? $request->query('token'));

        if ($device->provider !== 'qr' || $token === '' || ! hash_equals($token, $given)) {
            return response()->json(['received' => false, 'error' => 'Token tidak valid.'], 401);
        }

        $type = $request->input('type', 'message');

        if ($type === 'status') {
            $data = $request->validate([
                'id' => ['required', 'string', 'max:191'],
                'status' => ['required', Rule::in(['sent', 'delivered', 'read', 'failed'])],
                'error' => ['nullable', 'string', 'max:500'],
            ]);
            $message = Message::where('provider_message_id', $data['id'])->where('direction', 'out')->where('device_id', $device->id)->first();

            if ($message && self::RANK[$data['status']] > (self::RANK[$message->status] ?? 0)) {
                $message->forceFill(['status' => $data['status']] + ($data['status'] === 'failed' ? ['error' => 'Engine QR: '.($data['error'] ?? 'gagal terkirim')] : []))->save();

                if ($data['status'] === 'failed' && $message->campaign_id) {
                    \App\Models\CampaignRecipient::where('message_id', $message->id)->update(['status' => 'failed']);
                }

                $webhooks->dispatch('message.status', ['message' => $message->toApiArray()]);
            }

            return response()->json(['received' => true]);
        }

        if ($type === 'contacts') {
            $data = $request->validate(['contacts' => ['present', 'array', 'max:1000']]);
            $result = app(\App\Services\WaSyncService::class)->importContacts($data['contacts']);

            return response()->json(['received' => true] + $result);
        }

        if ($type === 'history') {
            $data = $request->validate(['messages' => ['present', 'array', 'max:500']]);

            return response()->json(['received' => true, 'saved' => app(\App\Services\WaSyncService::class)->importHistory($device, $data['messages'])]);
        }

        if ($type === 'groups') {
            $data = $request->validate(['groups' => ['present', 'array', 'max:2000']]);

            return response()->json(['received' => true, 'groups' => app(\App\Services\WaSyncService::class)->importGroups($device, $data['groups'], false)]);
        }

        if ($type === 'session') {
            $data = $request->validate(['status' => ['required', 'string', 'max:30'], 'phone' => ['nullable', 'string', 'max:30']]);
            $wasConnected = $device->status === 'connected';

            $device->forceFill(array_filter([
                'status' => $data['status'] === 'connected' ? 'connected' : 'disconnected',
                'phone_number' => $data['phone'] ?? null,
                'last_seen_at' => $data['status'] === 'connected' ? now() : null,
                'paired_at' => $data['status'] === 'connected' && ! $device->paired_at ? now() : null,
            ], fn ($v) => $v !== null))->save();

            if ($wasConnected && $data['status'] !== 'connected') {
                AppNotification::notify('device.disconnected', 'Perangkat '.$device->name.' terputus',
                    $data['status'] === 'logged_out' ? 'Perangkat logout dari WhatsApp. Scan QR ulang.' : 'Engine mencoba menyambung ulang…', route('devices.index'), 'admins');
            }

            return response()->json(['received' => true]);
        }

        $data = $request->validate([
            'from' => ['required_without:group', 'nullable', 'string', 'max:40'],
            'group' => ['nullable', 'array'],
            'group.jid' => ['required_with:group', 'string', 'max:64'],
            'group.subject' => ['nullable', 'string', 'max:191'],
            'message' => ['required_without:media', 'nullable', 'string', 'max:10000'],
            'id' => ['nullable', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:150'],
            'media' => ['nullable', 'array'],
            'media.mime' => ['required_with:media', 'string', 'max:100'],
            'media.name' => ['nullable', 'string', 'max:191'],
            'media.data' => ['required_with:media', 'string'],
        ]);

        $media = null;
        if (! empty($data['media'])) {
            $binary = base64_decode($data['media']['data'], true);
            if ($binary !== false && strlen($binary) <= \App\Services\MediaStorage::MAX_KB * 1024) {
                $media = app(\App\Services\MediaStorage::class)->storeBinary($binary, $data['media']['mime'], $data['media']['name'] ?? null);
            }
        }

        $message = ! empty($data['group'])
            ? $inbound->receiveGroup($device, $data['group'], $data['from'] ?? null, (string) ($data['message'] ?? ''), $data['id'] ?? null, $data['name'] ?? null, $media)
            : $inbound->receive($device, (string) $data['from'], (string) ($data['message'] ?? ''), $data['id'] ?? null, $data['name'] ?? null, $media);

        return response()->json(['received' => true, 'message_id' => $message?->public_id]);
    }
}
