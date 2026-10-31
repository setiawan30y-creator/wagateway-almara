<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Support\Facades\DB;

/**
 * Mesin broadcast dengan Smart Broadcast Protection:
 * consent check, blacklist (opt-out), duplicate, frequency cap, quiet hours,
 * rate limit, batas penerima, auto-pause berdasarkan kualitas.
 * @2026-Alifiandra
 */
class CampaignService
{
    public function __construct(
        private MessageService $messages,
        private WebhookDispatcher $webhooks,
    ) {}

    /** Hitung audience untuk pratinjau. */
    public function preview(array $audience): array
    {
        $base = Campaign::audienceQuery($audience);
        $total = (clone $base)->count();
        $eligible = (clone $base)->where('consent', 'opted_in')->count();
        $max = (int) Setting::get('max_recipients_per_campaign');

        return [
            'total' => $total,
            'eligible' => min($eligible, $max),
            'opted_out' => (clone $base)->where('consent', 'opted_out')->count(),
            'no_consent' => (clone $base)->where('consent', 'unknown')->count(),
            'over_limit' => max(0, $eligible - $max),
        ];
    }

    /** Bangun daftar penerima lalu set status running. */
    public function start(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return;
        }

        DB::transaction(function () use ($campaign) {
            $max = (int) Setting::get('max_recipients_per_campaign');
            $now = now();
            $count = 0;

            Campaign::audienceQuery($campaign->audience ?? [])
                ->orderBy('id')
                ->select(['id', 'consent'])
                ->chunkById(500, function ($contacts) use ($campaign, $max, $now, &$count) {
                    $rows = [];

                    foreach ($contacts as $c) {
                        $eligible = $c->consent === 'opted_in';
                        $over = $eligible && $count >= $max;

                        $rows[] = [
                            'campaign_id' => $campaign->id,
                            'contact_id' => $c->id,
                            'status' => $eligible && ! $over ? 'pending' : 'skipped',
                            'skip_reason' => match (true) {
                                $c->consent === 'opted_out' => 'opt-out',
                                ! $eligible => 'belum ada izin (opt-in)',
                                $over => 'melebihi batas penerima',
                                default => null,
                            },
                            'processed_at' => $eligible && ! $over ? null : $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        if ($eligible && ! $over) {
                            $count++;
                        }
                    }

                    // insertOrIgnore = proteksi duplikat (unique campaign_id + contact_id)
                    CampaignRecipient::insertOrIgnore($rows);
                });

            $campaign->forceFill(['status' => 'running', 'started_at' => $now, 'pause_reason' => null])->save();
        });
    }

    public function pause(Campaign $campaign, string $reason): void
    {
        if ($campaign->status !== 'running') {
            return;
        }

        $campaign->forceFill(['status' => 'paused', 'pause_reason' => $reason])->save();
    }

    public function resume(Campaign $campaign): void
    {
        if ($campaign->status === 'paused') {
            $campaign->forceFill(['status' => 'running', 'pause_reason' => null])->save();
        }
    }

    public function cancel(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $campaign->recipients()->where('status', 'pending')->update([
            'status' => 'skipped', 'skip_reason' => 'campaign dibatalkan', 'processed_at' => now(),
        ]);
        $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
    }

    /**
     * Proses satu batch (dipanggil scheduler tiap menit).
     * @return int jumlah pesan yang dikirim pada batch ini
     */
    public function runBatch(Campaign $campaign): int
    {
        if ($campaign->status !== 'running') {
            return 0;
        }

        if (! \App\Support\License::allows('broadcast')) {
            $this->autoPause($campaign, 'Fitur broadcast hanya tersedia di paket Pro.');

            return 0;
        }

        $device = $campaign->device;
        // Semua varian dari semua template yang dipilih -> diacak per penerima (anti-seragam)
        $templates = $campaign->usedTemplates()->keyBy('id');
        $variants = $templates->flatMap(fn ($t) => $t->variants)->values();

        if (! $device || ! $device->is_active || $variants->isEmpty()) {
            $this->autoPause($campaign, 'Perangkat atau template tidak tersedia.');

            return 0;
        }

        if (Setting::inQuietHours()) {
            return 0; // tunggu jam tenang selesai
        }

        // Rate limiter: maksimal rate_per_minute dalam 60 detik terakhir
        $recent = $campaign->recipients()->where('status', '!=', 'skipped')->where('processed_at', '>=', now()->subMinute())->count();
        $quota = max(0, $campaign->rate_per_minute - $recent);

        if (\App\Support\SendingQuota::applies($device)) {
            // Scan QR: engine mengirim satu per satu dengan jeda acak -> batasi per menit per nomor (semua campaign)
            $recentDevice = Message::where('device_id', $device->id)->where('direction', 'out')->whereNotNull('campaign_id')
                ->where('created_at', '>=', now()->subMinute())->count();
            $quota = min($quota, max(0, \App\Support\SendingQuota::maxPerMinute() - $recentDevice));

            // Batas harian + pemanasan nomor baru: sisa penerima menunggu besok
            $remaining = \App\Support\SendingQuota::remaining($device);
            if ($remaining !== null) {
                $quota = min($quota, $remaining);
            }
        }

        if ($quota === 0) {
            return 0;
        }

        $capHours = (int) Setting::get('frequency_cap_hours');
        $campaignMedia = $campaign->mediaFile();
        $bag = [];
        $sent = 0;

        $pending = $campaign->recipients()->with('contact')->where('status', 'pending')->orderBy('id')->limit($quota)->get();

        foreach ($pending as $recipient) {
            $contact = $recipient->contact;
            $reason = null;

            if (! $contact) {
                $reason = 'kontak dihapus';
            } elseif ($contact->consent !== 'opted_in') {
                $reason = $contact->consent === 'opted_out' ? 'opt-out' : 'belum ada izin (opt-in)';
            } elseif ($capHours > 0 && Message::where('contact_id', $contact->id)->whereNotNull('campaign_id')
                ->where('direction', 'out')->where('created_at', '>=', now()->subHours($capHours))->exists()) {
                $reason = "frequency cap ({$capHours} jam)";
            }

            if ($reason) {
                $recipient->forceFill(['status' => 'skipped', 'skip_reason' => $reason, 'processed_at' => now()])->save();

                continue;
            }

            // "kantong acak": tiap varian terpakai merata, tetapi urutannya acak
            if (empty($bag)) {
                $bag = $variants->shuffle()->all();
            }
            $variant = array_pop($bag);
            $body = Template::render($variant->body, $contact);
            // lampiran campaign diutamakan; bila kosong pakai lampiran milik template terpilih
            $media = $campaignMedia ?? $templates[$variant->template_id]?->mediaFile();

            $message = $this->messages->queueText($device, $contact->phone, $body, $campaign->creator, options: [
                'contact' => $contact,
                'campaign_id' => $campaign->id,
                'media' => $media, // lampiran campaign; teks template menjadi keterangan
            ]);

            $recipient->forceFill([
                'template_variant_id' => $variant->id,
                'message_id' => $message->id,
                'status' => $message->status === 'failed' ? 'failed' : 'sent',
                'processed_at' => now(),
            ])->save();

            $sent++;
        }

        $this->checkQuality($campaign->refresh());

        if ($campaign->status === 'running' && ! $campaign->recipients()->where('status', 'pending')->exists()) {
            $campaign->forceFill(['status' => 'completed', 'finished_at' => now()])->save();
            $stats = $campaign->stats();
            AppNotification::notify('campaign.finished', 'Campaign "'.$campaign->name.'" selesai',
                "Terkirim {$stats['sent']}, gagal {$stats['failed']}, dilewati {$stats['skipped']}.", route('campaigns.show', $campaign), 'admins');
            $this->webhooks->dispatch('campaign.finished', ['campaign' => $campaign->toApiArray()]);
        }

        return $sent;
    }

    /** Quality protection: jeda otomatis bila gagal / opt-out terlalu tinggi. */
    private function checkQuality(Campaign $campaign): void
    {
        if ($campaign->status !== 'running') {
            return;
        }

        $stats = $campaign->stats();
        $sample = $stats['sent'] + $stats['failed'];

        if ($sample < (int) Setting::get('auto_pause_min_sample')) {
            return;
        }

        if ($stats['failed_rate'] >= (float) Setting::get('auto_pause_failed_rate')) {
            $this->autoPause($campaign, "Tingkat gagal {$stats['failed_rate']}% melewati batas.");
        } elseif ($stats['opt_out_rate'] >= (float) Setting::get('auto_pause_optout_rate')) {
            $this->autoPause($campaign, "Tingkat opt-out {$stats['opt_out_rate']}% melewati batas.");
        }
    }

    private function autoPause(Campaign $campaign, string $reason): void
    {
        $this->pause($campaign, 'Otomatis: '.$reason);
        AppNotification::notify('campaign.paused', 'Campaign "'.$campaign->name.'" dijeda otomatis', $reason, route('campaigns.show', $campaign), 'admins');
        $this->webhooks->dispatch('campaign.finished', ['campaign' => $campaign->refresh()->toApiArray()]);
    }

    /** Jalankan semua campaign terjadwal & berjalan. */
    public function tick(): array
    {
        $started = 0;
        $sent = 0;

        Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()
            ->each(function (Campaign $c) use (&$started) {
                $this->start($c);
                $started++;
            });

        Campaign::where('status', 'running')->get()->each(function (Campaign $c) use (&$sent) {
            $sent += $this->runBatch($c);
        });

        return ['started' => $started, 'sent' => $sent];
    }
}
