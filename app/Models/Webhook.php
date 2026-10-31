<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Webhook keluar ke sistem pelanggan.
 * @2026-Alifiandra
 */
class Webhook extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    public const EVENTS = [
        'message.received' => 'Pesan masuk',
        'message.status' => 'Status pesan berubah',
        'contact.opted_out' => 'Kontak berhenti (opt-out)',
        'campaign.finished' => 'Campaign selesai / dijeda otomatis',
        'ticket.created' => 'Tiket dibuat',
        'ticket.updated' => 'Tiket diperbarui',
    ];

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active', 'last_status', 'last_called_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_called_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true) || in_array('*', $this->events ?? [], true);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'last_status' => $this->last_status,
            'last_called_at' => $this->last_called_at?->toIso8601String(),
        ];
    }
}
