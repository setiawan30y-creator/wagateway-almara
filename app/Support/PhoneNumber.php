<?php

namespace App\Support;

/**
 * Normalisasi nomor WhatsApp ke format internasional tanpa '+'.
 *   0812-3456-789  -> 628123456789
 *   +62 812 345    -> 62812345
 *   812345678      -> 62812345678
 * @2026-Alifiandra
 */
final class PhoneNumber
{
    public static function normalize(?string $input, ?string $countryCode = null): string
    {
        $countryCode ??= (string) config('whatsapp.default_country_code', '62');
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return $countryCode.substr($digits, 1);
        }

        if (str_starts_with($digits, '8') && $countryCode === '62') {
            return $countryCode.$digits;
        }

        return $digits;
    }

    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/^[1-9]\d{7,14}$/', $normalized);
    }
}
