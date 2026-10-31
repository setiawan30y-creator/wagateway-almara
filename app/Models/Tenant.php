<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penyewa (pelanggan aplikasi). Semua data operasional terpisah per penyewa.
 * @2026-Alifiandra
 */
class Tenant extends Model
{
    public const STATUSES = ['active' => 'Aktif', 'suspended' => 'Ditangguhkan'];

    protected $fillable = [
        'name', 'plan', 'max_devices', 'daily_messages', 'expires_at', 'status',
        'contact_name', 'contact_phone', 'license_id', 'last_reminder', 'notes',
    ];

    protected $attributes = ['plan' => 'free', 'status' => 'active'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'max_devices' => 'integer',
            'daily_messages' => 'integer',
        ];
    }

    private static ?int $defaultId = null;

    /** Penyewa pertama (pemilik aplikasi) — dipakai untuk konteks sistem / instalasi satu usaha. */
    public static function defaultId(): int
    {
        return self::$defaultId ??= (int) (static::orderBy('id')->value('id') ?? static::create(['name' => 'Usaha Utama', 'plan' => 'pro'])->id);
    }

    public static function forgetDefault(): void
    {
        self::$defaultId = null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScopes();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class)->withoutGlobalScopes();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TenantPayment::class)->latest('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Sisa hari sampai kedaluwarsa (negatif = sudah lewat; null = tanpa batas). */
    public function daysLeft(): ?int
    {
        return $this->expires_at ? (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false) : null;
    }

    public function planLabel(): string
    {
        return config('license.plans.'.$this->plan.'.label') ?? $this->plan;
    }
}
