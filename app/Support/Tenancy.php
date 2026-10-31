<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

/**
 * Konteks penyewa aktif untuk request / job / perintah yang sedang berjalan.
 * Tanpa konteks (mis. scheduler sebelum memilih penyewa) query tidak disaring.
 * @2026-Alifiandra
 */
final class Tenancy
{
    private static ?Tenant $current = null;

    public static function set(Tenant|int|null $tenant): void
    {
        $new = $tenant instanceof Tenant ? $tenant : ($tenant ? Tenant::find($tenant) : null);

        if (self::$current?->id !== $new?->id) {
            License::flush();
        }

        self::$current = $new;
    }

    public static function current(): ?Tenant
    {
        return self::$current;
    }

    public static function id(): ?int
    {
        return self::$current?->id;
    }

    public static function forget(): void
    {
        self::$current = null;
        License::flush();
        Tenant::forgetDefault();
    }

    /** Jalankan callback dalam konteks penyewa tertentu, lalu kembalikan konteks sebelumnya. */
    public static function run(Tenant|int|null $tenant, callable $callback): mixed
    {
        $previous = self::$current;
        self::set($tenant);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }

    /** Penyewa untuk pengguna yang login (super-admin bisa "masuk" ke penyewa lain). */
    public static function setFromUser(?User $user): void
    {
        if (! $user) {
            return;
        }

        $override = $user->is_superadmin && app()->bound('session') && session()->isStarted() ? session('tenant_override') : null;
        self::set($override ? (int) $override : $user->tenant_id);
    }

    /** Aturan validasi "exists" yang dibatasi ke penyewa aktif. */
    public static function exists(string $table, string $column = 'id'): \Illuminate\Validation\Rules\Exists
    {
        $rule = \Illuminate\Validation\Rule::exists($table, $column);

        return self::id() ? $rule->where('tenant_id', self::id()) : $rule;
    }

    /** Aturan validasi "unique" yang dibatasi ke penyewa aktif. */
    public static function unique(string $table, string $column): \Illuminate\Validation\Rules\Unique
    {
        $rule = \Illuminate\Validation\Rule::unique($table, $column);

        return self::id() ? $rule->where('tenant_id', self::id()) : $rule;
    }
}
