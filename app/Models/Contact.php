<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kontak / customer (CRM dasar).
 * @2026-Alifiandra
 */
class Contact extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    public const STAGES = [
        'new' => 'Lead baru',
        'contacted' => 'Dihubungi',
        'interested' => 'Tertarik',
        'negotiation' => 'Negosiasi',
        'payment' => 'Pembayaran',
        'customer' => 'Customer',
        'inactive' => 'Tidak aktif',
    ];

    public const CONSENTS = [
        'opted_in' => 'Izin (opt-in)',
        'unknown' => 'Belum diketahui',
        'opted_out' => 'Berhenti (opt-out)',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'stage', 'source', 'consent', 'consent_at',
        'assigned_to', 'notes', 'last_message_at', 'smart_link_id',
    ];

    protected $attributes = [
        'stage' => 'new',
        'consent' => 'unknown',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function smartLink(): BelongsTo
    {
        return $this->belongsTo(SmartLink::class);
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class)->latest('id');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $q;
        }

        $digits = preg_replace('/\D+/', '', $term);

        return $q->where(function ($w) use ($term, $digits) {
            $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            if ($digits !== '') {
                $w->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    /** Cari atau buat kontak berdasarkan nomor (sudah dinormalisasi). */
    public function recipientLists(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(RecipientList::class, 'contact_recipient_list');
    }

    public static function findOrCreateByPhone(string $phone, array $attributes = []): self
    {
        $phone = PhoneNumber::normalize($phone);

        return static::firstOrCreate(['phone' => $phone], $attributes);
    }

    public function displayName(): string
    {
        return $this->name ?: '+'.$this->phone;
    }

    public function maskedPhone(): string
    {
        $n = $this->phone;

        return strlen($n) > 7 ? substr($n, 0, 4).'•••'.substr($n, -3) : $n;
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    public function setConsent(string $consent, string $source, ?string $note = null): void
    {
        if (! array_key_exists($consent, self::CONSENTS) || $this->consent === $consent) {
            return;
        }

        $this->forceFill(['consent' => $consent, 'consent_at' => now()])->save();

        $this->consentEvents()->create([
            'consent' => $consent,
            'source' => $source,
            'note' => $note,
            'user_id' => auth()->id(),
        ]);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'stage' => $this->stage,
            'consent' => $this->consent,
            'tags' => $this->relationLoaded('tags') ? $this->tags->pluck('name')->values() : [],
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
