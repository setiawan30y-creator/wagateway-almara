<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mengirim satu event webhook dengan signature HMAC dan retry.
 * @2026-Alifiandra
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);
        $hook = $delivery?->webhook;

        if (! $delivery || ! $hook || ! $hook->is_active || $delivery->delivered_at) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $delivery->increment('attempts');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'ALMARA-Gateway-WA/'.config('whatsapp.version'),
                    'X-Almara-Event' => $delivery->event,
                    'X-Almara-Delivery' => $delivery->event_id,
                    'X-Almara-Timestamp' => (string) $timestamp,
                    'X-Almara-Signature' => WebhookDispatcher::sign((string) $hook->secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($hook->url);

            $status = $response->status();
            $ok = $response->successful();
            $error = $ok ? null : 'HTTP '.$status;
        } catch (Throwable $e) {
            $status = null;
            $ok = false;
            $error = mb_substr('Koneksi gagal: '.$e->getMessage(), 0, 250);
        }

        $delivery->forceFill([
            'response_status' => $status,
            'error' => $error,
            'delivered_at' => $ok ? now() : null,
        ])->save();

        $hook->forceFill(['last_status' => $status ?? 0, 'last_called_at' => now()])->save();

        $isSync = $this->job === null || $this->job->getConnectionName() === 'sync';

        if (! $ok && ! $isSync && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 600);
        }
    }
}
