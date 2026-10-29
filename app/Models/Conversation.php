<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Percakapan (satu per kontak) untuk Unified Inbox.
 * @2026-Alifiandra
 */
class Conversation extends Model
{
    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed'];

    public const PRIORITIES = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Urgent'];

    protected $fillable = [
        'contact_id', 'device_id', 'status', 'priority', 'assigned_to', 'ai_enabled', 'ai_paused_until', 'unread_count',
        'last_message_preview', 'last_direction', 'last_message_at', 'last_inbound_at',
    ];

    protected $attributes = [
        'status' => 'open',
        'priority' => 'normal',
        'unread_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'ai_enabled' => 'boolean',
            'ai_paused_until' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class)->latest('id');
    }

    public function aiPaused(): bool
    {
        return $this->ai_paused_until !== null && $this->ai_paused_until->isFuture();
    }

    /** Jendela layanan 24 jam (aturan Meta untuk pesan teks bebas). */
    public function withinServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subDay());
    }

    public static function forContact(Contact $contact, ?Device $device = null): self
    {
        $conversation = static::firstOrCreate(['contact_id' => $contact->id], ['device_id' => $device?->id]);

        if ($device && ! $conversation->device_id) {
            $conversation->forceFill(['device_id' => $device->id])->save();
        }

        return $conversation;
    }

    public function touchWith(Message $message): void
    {
        $inbound = $message->direction === 'in';

        $this->forceFill([
            'last_message_preview' => mb_substr($message->preview(), 0, 250),
            'last_direction' => $message->direction,
            'last_message_at' => $message->created_at ?? now(),
            'last_inbound_at' => $inbound ? now() : $this->last_inbound_at,
            'unread_count' => $inbound ? $this->unread_count + 1 : $this->unread_count,
            // pesan masuk membuka kembali percakapan yang ditutup
            'status' => $inbound && $this->status === 'closed' ? 'open' : $this->status,
        ])->save();
    }
}
