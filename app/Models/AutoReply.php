<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan balasan otomatis.
 *  trigger keyword : pesan cocok kata kunci (exact / contains / starts_with)
 *  trigger welcome : pesan pertama dari kontak baru
 *  trigger away    : pesan di luar jam kerja (Pengaturan)
 * @2026-Alifiandra
 */
class AutoReply extends Model
{
    public const TRIGGERS = ['keyword' => 'Kata kunci', 'welcome' => 'Sambutan kontak baru', 'away' => 'Di luar jam kerja'];

    public const MATCHES = ['contains' => 'Mengandung', 'exact' => 'Sama persis', 'starts_with' => 'Diawali'];

    protected $fillable = [
        'name', 'trigger', 'match_type', 'keywords', 'reply', 'device_id', 'add_tag_id',
        'cooldown_minutes', 'priority', 'is_active', 'hits_count', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_triggered_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'add_tag_id');
    }

    /** @return string[] */
    public function keywordList(): array
    {
        return collect(explode(',', (string) $this->keywords))->map(fn ($k) => mb_strtolower(trim($k)))->filter()->values()->all();
    }

    public function matches(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        foreach ($this->keywordList() as $kw) {
            $hit = match ($this->match_type) {
                'exact' => $text === $kw,
                'starts_with' => str_starts_with($text, $kw),
                default => (bool) preg_match('/(^|\W)'.preg_quote($kw, '/').'($|\W)/u', $text),
            };

            if ($hit) {
                return true;
            }
        }

        return false;
    }
}
