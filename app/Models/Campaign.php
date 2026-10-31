<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campaign broadcast.
 * @2026-Alifiandra
 */
class Campaign extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Terjadwal',
        'running' => 'Berjalan',
        'paused' => 'Dijeda',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'name', 'device_id', 'template_id', 'template_ids', 'media_type', 'media_path', 'media_name', 'media_mime', 'media_size', 'status', 'audience', 'rate_per_minute',
        'scheduled_at', 'started_at', 'finished_at', 'pause_reason', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'rate_per_minute' => 20];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'template_ids' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rate_per_minute' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** ID template yang dipakai (multi-template); kompatibel dengan campaign lama (template_id saja). */
    public function templateIds(): array
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($this->template_ids ?? []))));

        return $ids ?: array_filter([(int) $this->template_id]);
    }

    /** Template aktif + varian yang dipakai campaign ini. */
    public function usedTemplates(): \Illuminate\Support\Collection
    {
        return Template::with('variants')->whereIn('id', $this->templateIds())->orderBy('name')->get();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /** Query kontak sesuai audience (belum termasuk filter consent). */
    public static function audienceQuery(array $audience): Builder
    {
        $tags = array_filter((array) ($audience['tags'] ?? []));
        $stages = array_filter((array) ($audience['stages'] ?? []));
        $lists = array_filter((array) ($audience['lists'] ?? []));

        return Contact::query()
            ->when($lists, fn ($q) => $q->whereHas('recipientLists', fn ($l) => $l->whereIn('recipient_lists.id', $lists)))
            ->when($tags, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereIn('tags.id', $tags)))
            ->when($stages, fn ($q) => $q->whereIn('stage', $stages));
    }

    /** Statistik kesehatan campaign. */
    public function stats(): array
    {
        $r = $this->recipients()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $m = $this->messages()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        $total = (int) $r->sum();
        $sent = (int) (($m['sent'] ?? 0) + ($m['delivered'] ?? 0) + ($m['read'] ?? 0));
        $failed = (int) ($m['failed'] ?? 0);
        $attempted = $sent + $failed;

        $contactIds = $this->recipients()->where('status', 'sent')->pluck('contact_id');
        $replies = $this->started_at && $contactIds->isNotEmpty()
            ? Message::where('direction', 'in')->whereIn('contact_id', $contactIds)->where('created_at', '>', $this->started_at)->distinct()->count('contact_id')
            : 0;
        $optOuts = $this->started_at && $contactIds->isNotEmpty()
            ? ConsentEvent::where('consent', 'opted_out')->whereIn('contact_id', $contactIds)->where('created_at', '>=', $this->started_at)->distinct()->count('contact_id')
            : 0;

        $pct = fn ($n, $d) => $d > 0 ? round($n * 100 / $d, 1) : 0.0;

        return [
            'total' => $total,
            'pending' => (int) ($r['pending'] ?? 0),
            'skipped' => (int) ($r['skipped'] ?? 0),
            'sent' => $sent,
            'delivered' => (int) (($m['delivered'] ?? 0) + ($m['read'] ?? 0)),
            'read' => (int) ($m['read'] ?? 0),
            'failed' => $failed,
            'replies' => $replies,
            'opt_outs' => $optOuts,
            'failed_rate' => $pct($failed, $attempted),
            'reply_rate' => $pct($replies, $sent),
            'opt_out_rate' => $pct($optOuts, $sent),
            'progress' => $pct($total - (int) ($r['pending'] ?? 0), $total),
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'device_id' => $this->device_id,
            'template_id' => $this->template_id,
            'template_ids' => $this->templateIds(),
            'audience' => $this->audience,
            'rate_per_minute' => $this->rate_per_minute,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'pause_reason' => $this->pause_reason,
            'stats' => $this->stats(),
        ];
    }

    public function mediaFile(): ?\App\Support\MediaFile
    {
        return $this->media_type && $this->media_path
            ? new \App\Support\MediaFile($this->media_type, $this->media_path, (string) $this->media_name, (string) $this->media_mime, (int) $this->media_size)
            : null;
    }
}
