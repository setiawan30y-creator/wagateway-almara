<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template pesan dengan varian (A/B testing).
 * Variabel: {{name}} {{first_name}} {{phone}} {{email}}
 * Spintax: {Halo|Hai|Selamat siang} -> dipilih acak per penerima agar pesan tidak seragam.
 * Lampiran opsional (foto / file) ikut terkirim saat broadcast.
 * @2026-Alifiandra
 */
class Template extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    public const CATEGORIES = ['marketing' => 'Marketing', 'utility' => 'Utility / Info', 'service' => 'Layanan'];

    public const VARIABLES = ['name', 'first_name', 'phone', 'email'];

    protected $fillable = ['name', 'category', 'is_active', 'created_by', 'media_type', 'media_path', 'media_name', 'media_mime', 'media_size'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(TemplateVariant::class)->orderBy('label');
    }

    public static function render(string $body, ?Contact $contact): string
    {
        $name = $contact?->name ?: 'Pelanggan';
        $values = [
            'name' => $name,
            'first_name' => explode(' ', trim($name))[0],
            'phone' => $contact?->phone ?? '',
            'email' => $contact?->email ?? '',
        ];

        $text = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? $m[0], $body);

        return self::spin($text);
    }

    /** Spintax {a|b|c}: pilih salah satu secara acak (mendukung bersarang). */
    public static function spin(string $text): string
    {
        for ($i = 0; $i < 20 && preg_match('/\{([^{}]*\|[^{}]*)\}/', $text); $i++) {
            $text = preg_replace_callback('/\{([^{}]*\|[^{}]*)\}/', function ($m) {
                $options = explode('|', $m[1]);

                return $options[array_rand($options)];
            }, $text);
        }

        return $text;
    }

    public function mediaFile(): ?\App\Support\MediaFile
    {
        return $this->media_type && $this->media_path
            ? new \App\Support\MediaFile($this->media_type, $this->media_path, (string) $this->media_name, (string) $this->media_mime, (int) $this->media_size)
            : null;
    }
}
