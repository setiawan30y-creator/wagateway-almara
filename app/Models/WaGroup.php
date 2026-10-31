<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Grup WhatsApp yang diikuti perangkat Scan QR.
 * @2026-Alifiandra
 */
class WaGroup extends Model
{
    protected $table = 'wa_groups';

    protected $fillable = [
        'device_id', 'jid', 'subject', 'description', 'participants_count', 'participants', 'is_admin', 'announce', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'participants_count' => 'integer',
            'is_admin' => 'boolean',
            'announce' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public static function isJid(?string $value): bool
    {
        return (bool) preg_match('/^\d+(-\d+)?@g\.us$/', (string) $value);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'group_id');
    }

    /** Hanya admin yang boleh kirim & perangkat bukan admin. */
    public function canSend(): bool
    {
        return ! $this->announce || $this->is_admin;
    }

    /** Nomor anggota yang valid (tanpa nomor perangkat sendiri). */
    public function memberPhones(): array
    {
        $own = $this->device?->phone_number;

        return collect($this->participants ?? [])
            ->pluck('phone')
            ->filter(fn ($p) => $p && \App\Support\PhoneNumber::isValid((string) $p) && $p !== $own)
            ->unique()->values()->all();
    }

    /** Buat / perbarui grup dari data engine. */
    public static function upsertFromEngine(Device $device, array $g, bool $withParticipants = true): self
    {
        $group = static::firstOrNew(['device_id' => $device->id, 'jid' => (string) $g['jid']]);

        $group->fill([
            'subject' => mb_substr((string) ($g['subject'] ?? '') ?: '(tanpa nama)', 0, 191),
            'description' => isset($g['desc']) ? mb_substr((string) $g['desc'], 0, 5000) : $group->description,
            'participants_count' => (int) ($g['size'] ?? (isset($g['participants']) ? count($g['participants']) : $group->participants_count)),
            'is_admin' => (bool) ($g['is_admin'] ?? $group->is_admin),
            'announce' => (bool) ($g['announce'] ?? $group->announce),
            'synced_at' => now(),
        ]);

        if ($withParticipants && isset($g['participants']) && is_array($g['participants'])) {
            $group->participants = collect($g['participants'])
                ->map(fn ($p) => ['phone' => isset($p['phone']) ? preg_replace('/\D+/', '', (string) $p['phone']) : null, 'admin' => $p['admin'] ?? null])
                ->values()->all();
        }

        $group->save();

        return $group;
    }
}
