<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Services\WebhookDispatcher;
use App\WhatsApp\ProviderManager;
use App\WhatsApp\SendResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job pengiriman satu pesan WhatsApp, dengan retry untuk error sementara.
 * @2026-Alifiandra
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $messageId) {}

    public function handle(ProviderManager $providers, WebhookDispatcher $webhooks): void
    {
        $message = Message::with('device')->find($this->messageId);

        // Idempotency: jangan kirim ulang pesan yang sudah diproses.
        if (! $message || $message->status !== 'queued' || $message->direction !== 'out') {
            return;
        }

        $device = $message->device;

        if (! $device || ! $device->is_active) {
            $this->markFailed($message, 'Perangkat tidak ditemukan atau nonaktif.', $webhooks);

            return;
        }

        $message->increment('attempts');

        try {
            $provider = $providers->for($device);
            $media = $message->mediaFile();
            // Broadcast lewat Scan QR: kirim seperti manusia (jeda acak + "sedang mengetik…")
            $options = $message->campaign_id && $device->provider === 'qr' ? ['humanize' => \App\Support\SendingQuota::humanize()] : [];
            $result = $media
                ? $provider->sendMedia($device, $message->to, $media, $message->body !== '' ? $message->body : null, $options)
                : $provider->sendText($device, $message->to, $message->body, $options);
        } catch (Throwable $e) {
            Log::error('[WA] Gagal mengirim pesan', ['message' => $message->public_id, 'error' => $e->getMessage()]);
            $result = SendResult::failure('Kesalahan internal saat mengirim pesan.');
        }

        if ($result->ok) {
            $message->forceFill([
                'status' => 'sent',
                'provider_message_id' => $result->providerMessageId,
                'error' => null,
                'sent_at' => now(),
            ])->save();

            $device->forceFill(['status' => 'connected', 'last_seen_at' => now()])->save();
            $webhooks->dispatch('message.status', ['message' => $message->toApiArray()]);

            return;
        }

        $isSync = $this->job === null || $this->job->getConnectionName() === 'sync';

        if ($result->retryable && ! $isSync && $this->attempts() < $this->tries) {
            $message->forceFill(['error' => $result->error])->save();
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        $this->markFailed($message, (string) $result->error, $webhooks);
    }

    public function failed(?Throwable $exception): void
    {
        $message = Message::find($this->messageId);

        if ($message && $message->status === 'queued') {
            $this->markFailed($message, 'Job gagal: '.($exception?->getMessage() ?? 'tidak diketahui'), app(WebhookDispatcher::class));
        }
    }

    protected function markFailed(Message $message, string $error, WebhookDispatcher $webhooks): void
    {
        $message->forceFill(['status' => 'failed', 'error' => mb_substr($error, 0, 1000)])->save();

        if ($message->campaign_id) {
            CampaignRecipient::where('message_id', $message->id)->update(['status' => 'failed']);
        }

        $webhooks->dispatch('message.status', ['message' => $message->toApiArray()]);
    }
}
