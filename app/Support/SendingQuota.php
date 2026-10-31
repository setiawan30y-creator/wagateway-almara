<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;

/**
 * Pengaman broadcast perangkat Scan QR:
 * - jeda acak antar pesan + simulasi "sedang mengetik…" (dijalankan engine)
 * - batas broadcast harian per nomor + pemanasan (warm-up) nomor baru
 * @2026-Alifiandra
 */
final class SendingQuota
{
    /** Hari sejak tertaut => batas pesan broadcast per hari selama pemanasan. */
    public const WARMUP = [
        3 => 30,    // hari 1–3
        7 => 60,    // hari 4–7
        14 => 120,  // hari 8–14
        21 => 200,  // hari 15–21
    ];

    /** Opsi "kirim seperti manusia" untuk engine. */
    public static function humanize(): array
    {
        [$min, $max] = self::delayRange();

        return ['min_delay' => $min, 'max_delay' => $max, 'typing' => Setting::get('broadcast_typing') === '1'];
    }

    /** @return array{0: int, 1: int} jeda minimum & maksimum (detik) */
    public static function delayRange(): array
    {
        $min = max(0, (int) Setting::get('broadcast_delay_min'));
        $max = max($min, (int) Setting::get('broadcast_delay_max'));

        return [$min, $max];
    }

    /** Maks. pesan per menit yang masuk akal dengan jeda acak (agar antrean engine tidak menumpuk). */
    public static function maxPerMinute(): int
    {
        [$min, $max] = self::delayRange();
        $avg = ($min + $max) / 2 + (Setting::get('broadcast_typing') === '1' ? 3 : 0);

        return $avg <= 0 ? 120 : max(1, (int) floor(60 / $avg));
    }

    public static function applies(Device $device): bool
    {
        return $device->provider === 'qr';
    }

    /** Hari ke-berapa sejak nomor tertaut (1 = hari pertama). */
    public static function deviceAgeDays(Device $device): int
    {
        $since = $device->paired_at ?? $device->created_at ?? now();

        return (int) $since->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1;
    }

    /** Batas broadcast hari ini (null = tanpa batas). */
    public static function dailyLimit(Device $device): ?int
    {
        if (! self::applies($device)) {
            return null;
        }

        $limit = (int) Setting::get('broadcast_daily_limit');
        $limit = $limit > 0 ? $limit : null;

        if (Setting::get('warmup_enabled') === '1') {
            $age = self::deviceAgeDays($device);

            foreach (self::WARMUP as $untilDay => $cap) {
                if ($age <= $untilDay) {
                    return $limit === null ? $cap : min($cap, $limit);
                }
            }
        }

        return $limit;
    }

    public static function sentToday(Device $device): int
    {
        return Message::where('device_id', $device->id)->where('direction', 'out')
            ->whereNotNull('campaign_id')->where('created_at', '>=', now()->startOfDay())->count();
    }

    /** Sisa kuota broadcast hari ini (null = tanpa batas). */
    public static function remaining(Device $device): ?int
    {
        $limit = self::dailyLimit($device);

        return $limit === null ? null : max(0, $limit - self::sentToday($device));
    }

    /** Ringkasan untuk ditampilkan di panel. */
    public static function summary(Device $device): ?array
    {
        $limit = self::dailyLimit($device);

        if ($limit === null) {
            return null;
        }

        $age = self::deviceAgeDays($device);
        $warmup = Setting::get('warmup_enabled') === '1' && $age <= array_key_last(self::WARMUP);

        return [
            'limit' => $limit,
            'sent' => self::sentToday($device),
            'remaining' => max(0, $limit - self::sentToday($device)),
            'age' => $age,
            'warmup' => $warmup,
        ];
    }
}
