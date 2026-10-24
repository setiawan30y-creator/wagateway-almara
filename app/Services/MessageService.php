<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessage;
use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use App\Support\MediaFile;
use App\Support\PhoneNumber;

/**
 * Layanan pengiriman pesan: kontak + percakapan + log -> antrean -> provider.
 * @2026-Alifiandra
 */
class MessageService
{
    /**
     * @param  array{campaign_id?: ?int, contact?: ?Contact, source?: string, media?: ?MediaFile}  $options
     */
    public function queueText(Device $device, string $to, string $body, ?User $user = null, ?ApiKey $apiKey = null, ?string $idempotencyKey = null, array $options = []): Message
    {
        $phone = PhoneNumber::normalize($to);

        $contact = $options['contact'] ?? Contact::findOrCreateByPhone($phone, ['source' => $options['source'] ?? ($apiKey ? 'api' : 'panel')]);
        $conversation = Conversation::forContact($contact, $device);

        $message = Message::create([
            'device_id' => $device->id,
            'user_id' => $user?->id,
            'api_key_id' => $apiKey?->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'campaign_id' => $options['campaign_id'] ?? null,
            'direction' => 'out',
            'to' => $phone,
            'body' => $body,
            'media_type' => ($m = $options['media'] ?? null)?->type,
            'media_path' => $m?->path,
            'media_name' => $m?->name,
            'media_mime' => $m?->mime,
            'media_size' => $m?->size,
            'status' => 'queued',
            'idempotency_key' => $idempotencyKey,
        ]);

        $conversation->touchWith($message);
        $contact->forceFill(['last_message_at' => now()])->save();

        SendWhatsAppMessage::dispatch($message->id);

        return $message->refresh();
    }
}
