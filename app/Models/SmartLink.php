<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Smart Link: link klik-ke-chat WhatsApp dengan pelacakan.
 * Kode referensi [#slug] disisipkan di pesan awal agar chat yang masuk bisa diatribusikan.
 * @2026-Alifiandra
 */
class SmartLink extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'name', 'slug', 'device_id', 'phone', 'message', 'tag_id', 'utm_source', 'utm_medium', 'utm_campaign',
        'is_active', 'expires_at', 'clicks_count', 'chats_count', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(SmartLinkClick::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function targetPhone(): ?string
    {
        $phone = $this->phone ?: $this->device?->phone_number ?: Device::resolveDefault()?->phone_number;

        return $phone ? preg_replace('/\D+/', '', $phone) : null;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture()) && $this->targetPhone();
    }

    public function refCode(): string
    {
        return '[#'.$this->slug.']';
    }

    public function shortUrl(): string
    {
        return url('/l/'.$this->slug);
    }

    public function whatsappUrl(): string
    {
        $text = trim(($this->message ?? '').' '.$this->refCode());

        return 'https://wa.me/'.$this->targetPhone().'?text='.rawurlencode($text);
    }

    /** Konversi = kontak dari link ini yang sudah menjadi customer. */
    public function conversions(): int
    {
        return $this->contacts()->where('stage', 'customer')->count();
    }

    /** Cari smart link dari kode [#slug] di isi pesan. */
    public static function fromMessage(string $body): ?self
    {
        return preg_match('/\[#([a-z0-9][a-z0-9-]{0,59})\]/i', $body, $m) ? static::where('slug', strtolower($m[1]))->first() : null;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->shortUrl(),
            'whatsapp_url' => $this->targetPhone() ? $this->whatsappUrl() : null,
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'clicks' => $this->clicks_count,
            'chats' => $this->chats_count,
            'conversions' => $this->conversions(),
        ];
    }
}
