<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * API key untuk akses REST API /api/v1.
 * Database menyimpan hash SHA-256 (untuk verifikasi) + salinan terenkripsi APP_KEY (untuk
 * ditampilkan & disalin dari halaman Perangkat / API Key). Key bisa terikat ke satu perangkat:
 * pesan lewat key itu otomatis dikirim dari nomor tersebut (seperti token device Fonnte).
 * @2026-Alifiandra
 */
class ApiKey extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['user_id', 'device_id', 'name', 'prefix', 'key_hash', 'secret', 'last_used_at', 'revoked_at'];

    protected $hidden = ['key_hash', 'secret'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Membuat API key baru. Mengembalikan [model, plainTextKey].
     *
     * @return array{0: ApiKey, 1: string}
     */
    public static function generate(User $user, string $name, ?Device $device = null): array
    {
        $plain = 'alm_'.Str::random(40);

        $key = static::create([
            'user_id' => $user->id,
            'device_id' => $device?->id,
            'name' => $name,
            'prefix' => substr($plain, 0, 10),
            'key_hash' => static::hash($plain),
            'secret' => $plain,
        ]);

        return [$key, $plain];
    }

    public static function findActiveByPlain(string $plain): ?self
    {
        return static::active()->where('key_hash', static::hash($plain))->first();
    }

    /** Token perangkat: cabut token lama perangkat ini lalu buat yang baru. */
    public static function forDevice(Device $device, User $user): array
    {
        static::active()->where('device_id', $device->id)->update(['revoked_at' => now()]);

        return static::generate($user, 'Token '.$device->name, $device);
    }

    /** Key penuh (null untuk key lama yang hanya tersimpan hash-nya). */
    public function plainKey(): ?string
    {
        try {
            return $this->secret;
        } catch (\Throwable) {
            return null; // APP_KEY berubah
        }
    }

    /**
     * Perangkat pengirim: device_id dari request > perangkat milik token > nomor utama.
     */
    public function resolveDevice(?int $deviceId = null): ?Device
    {
        if ($deviceId) {
            return Device::where('is_active', true)->find($deviceId);
        }

        if ($this->device_id && ($own = Device::where('is_active', true)->find($this->device_id))) {
            return $own;
        }

        return Device::resolveDefault();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
