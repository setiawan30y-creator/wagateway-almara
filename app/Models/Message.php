<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Log pesan WhatsApp keluar.
 * @2026-Alifiandra
 */
class Message extends Model
{
    public const STATUSES = ['queued', 'sent', 'delivered', 'read', 'failed', 'received'];

    protected $fillable = [
        'public_id', 'device_id', 'user_id', 'api_key_id', 'contact_id', 'conversation_id', 'campaign_id', 'direction', 'to', 'body',
        'status', 'provider_message_id', 'error', 'attempts', 'idempotency_key', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->public_id ??= 'msg_'.Str::lower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** Nomor disamarkan untuk tampilan tabel: 6281•••789 */
    public function maskedTo(): string
    {
        $n = $this->to;

        return strlen($n) > 7 ? substr($n, 0, 4).'•••'.substr($n, -3) : $n;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->public_id,
            'direction' => $this->direction,
            'to' => $this->to,
            'contact_id' => $this->contact_id,
            'campaign_id' => $this->campaign_id,
            'body' => $this->body,
            'status' => $this->status,
            'device_id' => $this->device_id,
            'provider_message_id' => $this->provider_message_id,
            'error' => $this->status === 'failed' ? $this->error : null,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
