<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Mengirim event ke webhook keluar yang berlangganan.
 * @2026-Alifiandra
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $data): void
    {
        $hooks = Webhook::where('is_active', true)->get()->filter->listensTo($event);

        foreach ($hooks as $hook) {
            $eventId = 'evt_'.Str::lower((string) Str::ulid());

            $delivery = $hook->deliveries()->create([
                'event_id' => $eventId,
                'event' => $event,
                'payload' => [
                    'id' => $eventId,
                    'event' => $event,
                    'created_at' => now()->toIso8601String(),
                    'data' => $data,
                ],
            ]);

            // Mode sync: kirim setelah respons agar request tidak tertahan.
            // Mode queue (database/redis): diproses worker dengan retry.
            if (config('queue.default') === 'sync') {
                DeliverWebhook::dispatchAfterResponse($delivery->id);
            } else {
                DeliverWebhook::dispatch($delivery->id);
            }
        }
    }

    /** Header tanda tangan: X-Almara-Signature = sha256=HMAC(secret, timestamp + "." + body) */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
