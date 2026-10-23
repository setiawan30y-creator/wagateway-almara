<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Meta Cloud API: verifikasi + pembaruan status pesan (sent/delivered/read/failed).
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

    public function receive(Request $request)
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
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $this->applyStatus($status);
                }

                if ($count = count((array) data_get($change, 'value.messages', []))) {
                    // Inbox belum termasuk versi sederhana ini; cukup dicatat.
                    Log::info('[WA-META] Pesan masuk diterima', ['count' => $count]);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $new = $status['status'] ?? null;

        if (! $id || ! isset(self::RANK[$new])) {
            return;
        }

        $message = Message::where('provider_message_id', $id)->first();

        // Abaikan callback duplikat / out-of-order (status tidak boleh mundur).
        if (! $message || self::RANK[$new] <= (self::RANK[$message->status] ?? 0)) {
            return;
        }

        $message->status = $new;

        if ($new === 'failed') {
            $message->error = 'Meta: '.(data_get($status, 'errors.0.title') ?? 'pengiriman gagal');
        }

        $message->save();
    }
}
