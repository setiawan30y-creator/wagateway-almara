<?php

namespace App\Support;

use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Paket aktif (Gratis / Pro) berdasarkan kode lisensi bertanda tangan.
 * Kode: base64url(payload JSON) . "." . base64url(tanda tangan ECDSA-SHA256)
 * Payload: {"id","name","plan","devices","exp":"YYYY-MM-DD"|null,"iat"}
 * @2026-Alifiandra
 */
final class License
{
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Info lisensi aktif + batas paket. */
    public static function current(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $forced = config('license.force_plan');
        if ($forced && app()->environment('testing') && isset(config('license.plans')[$forced])) {
            return self::$cache = self::build($forced, ['name' => 'Testing']);
        }

        $key = (string) Setting::get('license_key', '');
        $payload = $key !== '' ? self::verify($key) : null;

        if (! $payload) {
            return self::$cache = self::build('free', [], $key !== '' ? 'Kode lisensi tidak valid.' : null);
        }

        $exp = ! empty($payload['exp']) ? Carbon::parse($payload['exp'])->endOfDay() : null;
        $grace = (int) config('license.grace_days', 0);

        if ($exp && $exp->copy()->addDays($grace)->isPast()) {
            return self::$cache = self::build('free', $payload, 'Lisensi kedaluwarsa pada '.$exp->format('d/m/Y').'.');
        }

        return self::$cache = self::build((string) $payload['plan'], $payload, $exp && $exp->isPast() ? 'Lisensi sudah kedaluwarsa — masa tenggang sampai '.$exp->copy()->addDays($grace)->format('d/m/Y').'.' : null);
    }

    private static function build(string $plan, array $payload, ?string $warning = null): array
    {
        $limits = config('license.plans.'.$plan) ?? config('license.plans.free');
        $plan = config('license.plans.'.$plan) ? $plan : 'free';

        if ($plan !== 'free' && isset($payload['devices'])) {
            $limits['devices'] = $payload['devices'] === null ? null : (int) $payload['devices'];
        }

        return [
            'plan' => $plan,
            'label' => $limits['label'],
            'limits' => $limits,
            'licensee' => $payload['name'] ?? null,
            'id' => $payload['id'] ?? null,
            'expires' => ! empty($payload['exp']) ? Carbon::parse($payload['exp']) : null,
            'warning' => $warning,
        ];
    }

    public static function plan(): string
    {
        return self::current()['plan'];
    }

    public static function isPro(): bool
    {
        return self::plan() !== 'free';
    }

    public static function allows(string $feature): bool
    {
        return (bool) (self::current()['limits'][$feature] ?? false);
    }

    public static function limit(string $key): ?int
    {
        $v = self::current()['limits'][$key] ?? null;

        return $v === null ? null : (int) $v;
    }

    // --- Batas yang dipakai aplikasi -------------------------------------------------

    /** Teks watermark (null bila paket tidak memakai watermark). */
    public static function watermark(): ?string
    {
        return self::allows('watermark') ? (string) config('license.watermark_text') : null;
    }

    public static function applyWatermark(string $body): string
    {
        $mark = self::watermark();

        return $mark === null || str_contains($body, trim($mark)) ? $body : rtrim($body).$mark;
    }

    /** Jumlah nomor sungguhan (perangkat Simulasi tidak dihitung). */
    public static function realDevices(): int
    {
        return Device::where('provider', '!=', 'log')->count();
    }

    /** Boleh menambah perangkat baru? (Simulasi selalu boleh) */
    public static function canAddDevice(string $provider = 'qr'): bool
    {
        $max = self::limit('devices');

        return $provider === 'log' || $max === null || self::realDevices() < $max;
    }

    /** Perangkat termasuk dalam kuota paket (urut ID; kelebihan setelah turun paket tidak bisa kirim). */
    public static function deviceAllowed(Device $device): bool
    {
        $max = self::limit('devices');

        return $device->provider === 'log' || $max === null
            || Device::where('provider', '!=', 'log')->orderBy('id')->limit($max)->pluck('id')->contains($device->id);
    }

    public static function messagesToday(): int
    {
        return Message::where('direction', 'out')->where('created_at', '>=', now()->startOfDay())
            ->where('status', '!=', 'failed')->count();
    }

    /** Sisa pesan hari ini (null = tanpa batas). */
    public static function remainingMessages(): ?int
    {
        $max = self::limit('daily_messages');

        return $max === null ? null : max(0, $max - self::messagesToday());
    }

    // --- Kode lisensi ----------------------------------------------------------------

    public static function publicKey(): ?string
    {
        $path = config('license.public_key_path');

        return $path && is_file($path) ? (string) file_get_contents($path) : null;
    }

    public static function privateKeyPath(): string
    {
        if ($p = config('license.private_key_path')) {
            return $p;
        }

        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: storage_path('app');

        return rtrim($home, '\\/').DIRECTORY_SEPARATOR.'.almara-license'.DIRECTORY_SEPARATOR.'private.pem';
    }

    /** Periksa kode lisensi; mengembalikan payload bila sah. */
    public static function verify(string $key): ?array
    {
        $pub = self::publicKey();
        $parts = explode('.', trim($key));

        if (! $pub || count($parts) !== 2) {
            return null;
        }

        [$body, $sig] = $parts;
        $signature = self::b64d($sig);

        if ($signature === false || openssl_verify($body, $signature, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $payload = json_decode((string) self::b64d($body), true);

        return is_array($payload) && isset($payload['plan']) ? $payload : null;
    }

    /** Terbitkan kode lisensi (butuh kunci privat). */
    public static function issue(array $payload, string $privateKeyPem): string
    {
        $body = self::b64e((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private($privateKeyPem);

        if (! $key || ! openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Kunci privat tidak valid.');
        }

        return $body.'.'.self::b64e($signature);
    }

    /** @return array{private: string, public: string} */
    public static function generateKeyPair(): array
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

        // PHP di Windows (Laragon) kadang butuh lokasi openssl.cnf
        $cnf = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
        if (! getenv('OPENSSL_CONF') && is_file($cnf)) {
            $options['config'] = $cnf;
        }

        $res = openssl_pkey_new($options);

        if (! $res) {
            throw new \RuntimeException('OpenSSL tidak dapat membuat kunci: '.openssl_error_string());
        }

        openssl_pkey_export($res, $private, null, isset($options['config']) ? ['config' => $options['config']] : []);

        return ['private' => $private, 'public' => openssl_pkey_get_details($res)['key']];
    }

    private static function b64e(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function b64d(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
