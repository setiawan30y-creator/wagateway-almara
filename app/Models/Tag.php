<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @2026-Alifiandra */
class Tag extends Model
{
    protected $fillable = ['name', 'color'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    /** Ubah "vip, promo" menjadi daftar id tag (dibuat bila belum ada). */
    public static function idsFromString(?string $input): array
    {
        return collect(preg_split('/[,;]+/', (string) $input))
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->filter()
            ->unique()
            ->map(fn ($name) => static::firstOrCreate(['name' => mb_substr($name, 0, 50)])->id)
            ->values()
            ->all();
    }
}
