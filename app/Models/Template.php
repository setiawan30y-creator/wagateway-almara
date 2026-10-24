<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Template pesan dengan varian (A/B testing).
 * Variabel: {{name}} {{first_name}} {{phone}} {{email}}
 * @2026-Alifiandra
 */
class Template extends Model
{
    public const CATEGORIES = ['marketing' => 'Marketing', 'utility' => 'Utility / Info', 'service' => 'Layanan'];

    public const VARIABLES = ['name', 'first_name', 'phone', 'email'];

    protected $fillable = ['name', 'category', 'is_active', 'created_by'];

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

        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? $m[0], $body);
    }
}
