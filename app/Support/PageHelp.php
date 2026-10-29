<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mencari isi bantuan untuk halaman yang sedang dibuka (config/help.php).
 * @2026-Alifiandra
 */
final class PageHelp
{
    public static function for(?string $routeName): array
    {
        $all = (array) config('help', []);
        $routeName = (string) $routeName;

        if (isset($all[$routeName])) {
            return self::normalize($all[$routeName]);
        }

        foreach ($all as $pattern => $help) {
            if ($pattern !== '*' && str_contains($pattern, '*') && Str::is($pattern, $routeName)) {
                return self::normalize($help);
            }
        }

        return self::normalize($all['*'] ?? []);
    }

    private static function normalize(array $h): array
    {
        return [
            'judul' => $h['judul'] ?? 'Bantuan',
            'fungsi' => $h['fungsi'] ?? '',
            'langkah' => $h['langkah'] ?? [],
            'tips' => $h['tips'] ?? [],
            'doc' => $h['doc'] ?? 'fitur',
        ];
    }
}
