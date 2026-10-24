<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Notifikasi dalam aplikasi (fallback bila push browser tidak tersedia).
 * @2026-Alifiandra
 */
class AppNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /**
     * Kirim notifikasi ke pengguna aktif.
     * $audience: 'all' | 'admins' | User id
     */
    public static function notify(string $type, string $title, ?string $body = null, ?string $url = null, string|int $audience = 'all'): void
    {
        $users = is_int($audience)
            ? User::whereKey($audience)->where('is_active', true)->pluck('id')
            : User::where('is_active', true)
                ->when($audience === 'admins', fn ($q) => $q->whereIn('role', ['owner', 'admin']))
                ->pluck('id');

        $now = now();
        $rows = $users->map(fn ($id) => [
            'user_id' => $id,
            'type' => $type,
            'title' => mb_substr($title, 0, 150),
            'body' => $body ? mb_substr($body, 0, 255) : null,
            'url' => $url,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows) {
            static::insert($rows);
        }
    }
}
