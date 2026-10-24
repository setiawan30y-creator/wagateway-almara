<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\InboundService;
use Illuminate\Http\Request;

/**
 * Pesan masuk dari engine QR Multi-Device.
 * POST {APP_URL}/api/webhooks/qr/{device}  header X-Webhook-Token
 * body: {"from": "628...", "message": "...", "id": "...", "name": "..."}
 * @2026-Alifiandra
 */
class QrWebhookController extends Controller
{
    public function __invoke(Request $request, Device $device, InboundService $inbound)
    {
        $token = (string) $device->credential('webhook_token');
        $given = (string) ($request->header('X-Webhook-Token') ?? $request->query('token'));

        if ($device->provider !== 'qr' || $token === '' || ! hash_equals($token, $given)) {
            return response()->json(['received' => false, 'error' => 'Token tidak valid.'], 401);
        }

        $data = $request->validate([
            'from' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:10000'],
            'id' => ['nullable', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $message = $inbound->receive($device, $data['from'], $data['message'], $data['id'] ?? null, $data['name'] ?? null);

        return response()->json(['received' => true, 'message_id' => $message?->public_id]);
    }
}
