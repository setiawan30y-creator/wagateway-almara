<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * API key untuk akses REST API /api/v1.
 * Key penuh hanya ditampilkan sekali saat dibuat; database menyimpan hash SHA-256.
 * @2026-Alifiandra
 */
class ApiKey extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['user_id', 'name', 'prefix', 'key_hash', 'last_used_at', 'revoked_at'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
    public static function generate(User $user, string $name): array
    {
        $plain = 'alm_'.Str::random(40);

        $key = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'prefix' => substr($plain, 0, 10),
            'key_hash' => static::hash($plain),
        ]);

        return [$key, $plain];
    }

    public static function findActiveByPlain(string $plain): ?self
    {
        return static::active()->where('key_hash', static::hash($plain))->first();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
