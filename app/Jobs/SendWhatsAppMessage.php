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
        $message = Message::withoutGlobalScopes()->find($this->messageId);

        if ($message) {
            \App\Support\Tenancy::set($message->tenant_id);
            $message->load('device');

            if ($message->status === 'queued' && ! \App\Support\Tenancy::current()?->isActive()) {
                $this->markFailed($message, 'Akun usaha ditangguhkan.', app(WebhookDispatcher::class));

                return;
            }
        }

        // Idempotency: jangan kirim ulang pesan yang sudah diproses.
        if (! $message || $message->status !== 'queued' || $message->direction !== 'out') {
            return;
        }

        $device = $message->device;

        if (! $device || ! $device->is_active) {
            $this->markFailed($message, 'Perangkat tidak ditemukan atau nonaktif.', $webhooks);

            return;
        }

        // Batas paket (Gratis / Pro)
        if (! \App\Support\License::deviceAllowed($device)) {
            $this->markFailed($message, 'Nomor ini melebihi batas perangkat paket '.\App\Support\License::current()['label'].'. Upgrade lisensi untuk memakai lebih banyak nomor.', $webhooks);

            return;
        }

        if (($max = \App\Support\License::limit('daily_messages')) !== null
            && Message::where('direction', 'out')->where('created_at', '>=', now()->startOfDay())->where('status', '!=', 'failed')->where('id', '<', $message->id)->count() >= $max) {
            $this->markFailed($message, "Batas {$max} pesan/hari paket ".\App\Support\License::current()['label'].' tercapai. Upgrade ke Pro untuk tanpa batas.', $webhooks);

            return;
        }

        $message->increment('attempts');

        try {
            $provider = $providers->for($device);
            $media = $message->mediaFile();
            // Broadcast lewat Scan QR: kirim seperti manusia (jeda acak + "sedang mengetik…")
            $options = $message->campaign_id && $device->provider === 'qr' ? ['humanize' => \App\Support\SendingQuota::humanize()] : [];
            // Paket Gratis: watermark di akhir pesan / keterangan lampiran (audio tidak punya keterangan)
            $text = \App\Support\License::applyWatermark($message->body);
            $caption = $message->body !== '' ? $text : (\App\Support\License::watermark() !== null ? trim(\App\Support\License::watermark()) : null);
            $result = $media
                ? $provider->sendMedia($device, $message->to, $media, $media->type === 'audio' ? null : $caption, $options)
                : $provider->sendText($device, $message->to, $text, $options);
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
