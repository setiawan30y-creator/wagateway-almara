<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tiket helpdesk.
 * @2026-Alifiandra
 */
class Ticket extends Model
{
    public const STATUSES = ['open' => 'Open', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public const PRIORITIES = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Urgent'];

    public const CATEGORIES = ['general' => 'Umum', 'complaint' => 'Komplain', 'billing' => 'Tagihan / pembayaran', 'technical' => 'Teknis', 'order' => 'Pesanan / pengiriman'];

    protected $fillable = [
        'number', 'contact_id', 'conversation_id', 'subject', 'description', 'category', 'priority',
        'status', 'assigned_to', 'created_by', 'sla_due_at', 'escalated_at', 'resolved_at',
    ];

    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'category' => 'general'];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Ticket $t) {
            if (! $t->number) {
                $t->forceFill(['number' => 'TCK-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->latest('id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'pending']);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('sla_due_at')->where('sla_due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['open', 'pending'], true) && $this->sla_due_at && $this->sla_due_at->isPast();
    }

    /** Batas SLA (jam) per prioritas dari pengaturan. */
    public static function slaHours(string $priority): int
    {
        return (int) Setting::get('sla_'.$priority.'_hours', (string) (['urgent' => 4, 'high' => 8, 'normal' => 24, 'low' => 72][$priority] ?? 24));
    }

    public function log(string $type, ?string $body = null): void
    {
        $this->events()->create(['type' => $type, 'body' => $body, 'user_id' => auth()->id()]);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'contact_id' => $this->contact_id,
            'assigned_to' => $this->assigned_to,
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'overdue' => $this->isOverdue(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
