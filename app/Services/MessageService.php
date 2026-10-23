<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use App\Support\PhoneNumber;

/**
 * Layanan pengiriman pesan: simpan log -> antrean -> provider.
 * @2026-Alifiandra
 */
class MessageService
{
    public function queueText(
        Device $device,
        string $to,
        string $body,
        ?User $user = null,
        ?ApiKey $apiKey = null,
        ?string $idempotencyKey = null,
    ): Message {
        $message = Message::create([
            'device_id' => $device->id,
            'user_id' => $user?->id,
            'api_key_id' => $apiKey?->id,
            'direction' => 'out',
            'to' => PhoneNumber::normalize($to),
            'body' => $body,
            'status' => 'queued',
            'idempotency_key' => $idempotencyKey,
        ]);

        SendWhatsAppMessage::dispatch($message->id);

        return $message->refresh();
    }
}
