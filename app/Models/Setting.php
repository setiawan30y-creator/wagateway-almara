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
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value'];

    /** ID penyewa pemilik pengaturan (konteks aktif, atau penyewa utama untuk konteks sistem). */
    public static function tenantKey(): int
    {
        return \App\Support\Tenancy::id() ?? Tenant::defaultId();
    }

    public const DEFAULTS = [
        'quiet_hours_enabled' => '1',
        'require_opt_in' => '1',          // broadcast hanya ke kontak opt-in
        'quiet_start' => '21:00',
        'quiet_end' => '08:00',
        'frequency_cap_hours' => '24',
        'default_rate_per_minute' => '20',
        'max_recipients_per_campaign' => '1000',
        'auto_pause_failed_rate' => '20',
        'auto_pause_optout_rate' => '5',
        'auto_pause_min_sample' => '20',
        // Pengaman broadcast perangkat Scan QR
        'broadcast_delay_min' => '8',    // detik
        'broadcast_delay_max' => '25',   // detik
        'broadcast_typing' => '1',       // tampilkan "sedang mengetik…"
        'broadcast_daily_limit' => '300', // per nomor per hari, 0 = tanpa batas
        'warmup_enabled' => '1',         // pemanasan nomor baru
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
        // AI Agent (OpenRouter / API kompatibel OpenAI)
        'ai_enabled' => '0',
        'ai_base_url' => 'https://openrouter.ai/api/v1',
        'ai_api_key' => '', // disimpan terenkripsi
        'ai_model' => 'openrouter/free',
        'ai_agent_name' => 'Asisten Virtual',
        'ai_persona' => 'Kamu adalah customer service yang ramah, sopan, dan membantu. Jawab singkat dan jelas seperti chat WhatsApp.',
        'ai_rules' => '',
        'ai_mode' => 'always', // always | outside_hours | unassigned
        'ai_handoff_keywords' => 'cs, admin, operator, manusia, komplain, bicara dengan orang',
        'ai_handoff_message' => 'Baik, kami sambungkan ke tim customer service. Mohon tunggu sebentar ya.',
        'ai_pause_minutes' => '60',
        'ai_max_per_contact_hour' => '10',
        'ai_daily_limit' => '900',
        'ai_temperature' => '0.4',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $tid = static::tenantKey();
        $all = Cache::rememberForever('settings.all.'.$tid, fn () => static::withoutGlobalScopes()->where('tenant_id', $tid)->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, ?string $value): void
    {
        $tid = static::tenantKey();
        static::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tid, 'key' => $key], ['value' => $value]);
        Cache::forget('settings.all.'.$tid);
    }

    /** Nilai rahasia (terenkripsi). */
    public static function secret(string $key): string
    {
        $value = (string) static::get($key, '');

        if ($value === '') {
            return '';
        }

        try {
            return (string) \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function setSecret(string $key, string $plain): void
    {
        static::set($key, $plain === '' ? '' : \Illuminate\Support\Facades\Crypt::encryptString($plain));
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
