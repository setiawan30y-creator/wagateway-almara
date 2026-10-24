<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan gateway (key-value) dengan nilai default.
 * @2026-Alifiandra
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'quiet_hours_enabled' => '1',
        'quiet_start' => '21:00',
        'quiet_end' => '08:00',
        'frequency_cap_hours' => '24',
        'default_rate_per_minute' => '20',
        'max_recipients_per_campaign' => '1000',
        'auto_pause_failed_rate' => '20',
        'auto_pause_optout_rate' => '5',
        'auto_pause_min_sample' => '20',
        'stop_keywords' => 'stop, berhenti, unsubscribe, unreg',
        'start_keywords' => 'start, mulai, daftar',
        'stop_reply' => 'Anda telah berhenti berlangganan pesan dari kami. Balas MULAI untuk berlangganan kembali.',
        'start_reply' => 'Terima kasih, Anda kembali berlangganan informasi dari kami.',
        'auto_assign' => 'none', // none | round_robin
        'sla_urgent_hours' => '4',
        'sla_high_hours' => '8',
        'sla_normal_hours' => '24',
        'sla_low_hours' => '72',
        'work_days' => '1,2,3,4,5,6', // 1 = Senin ... 7 = Minggu
        'work_start' => '08:00',
        'work_end' => '17:00',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings.all', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    public static function keywords(string $key): array
    {
        return collect(explode(',', (string) static::get($key)))
            ->map(fn ($k) => mb_strtolower(trim($k)))
            ->filter()
            ->values()
            ->all();
    }

    /** Apakah sekarang di dalam jam kerja (untuk auto-reply di luar jam kerja)? */
    public static function inWorkingHours(?\DateTimeInterface $at = null): bool
    {
        $now = $at ? \Illuminate\Support\Carbon::instance($at) : now();
        $days = array_map('intval', array_filter(explode(',', (string) static::get('work_days'))));

        if (! in_array($now->dayOfWeekIso, $days, true)) {
            return false;
        }

        $t = $now->format('H:i');

        return $t >= (string) static::get('work_start') && $t < (string) static::get('work_end');
    }

    /** Apakah waktu sekarang berada di jam tenang (quiet hours)? */
    public static function inQuietHours(?\DateTimeInterface $at = null): bool
    {
        if (static::get('quiet_hours_enabled') !== '1') {
            return false;
        }

        $now = ($at ? \Illuminate\Support\Carbon::instance($at) : now())->format('H:i');
        $start = (string) static::get('quiet_start');
        $end = (string) static::get('quiet_end');

        if ($start === $end) {
            return false;
        }

        return $start < $end
            ? ($now >= $start && $now < $end)
            : ($now >= $start || $now < $end); // melewati tengah malam
    }
}
