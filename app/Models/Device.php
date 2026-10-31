<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perangkat / nomor WhatsApp yang terhubung ke gateway.
 * Kredensial provider disimpan terenkripsi (APP_KEY).
 * @2026-Alifiandra
 */
class Device extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'name', 'provider', 'phone_number', 'credentials',
        'status', 'is_default', 'is_active', 'last_seen_at', 'paired_at',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'paired_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials ?? [], $key, $default);
    }

    public function providerLabel(): string
    {
        return config("whatsapp.providers.{$this->provider}.label", $this->provider);
    }

    public static function resolveDefault(): ?self
    {
        return static::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    /** Nomor sungguhan pertama yang tersambung otomatis menggantikan perangkat Simulasi sebagai nomor utama. */
    public function promoteIfSimulationDefault(): void
    {
        $current = static::query()->where('is_default', true)->first();

        if ($this->provider !== 'log' && (! $current || $current->provider === 'log')) {
            $this->makeDefault();
        }
    }

    public function makeDefault(): void
    {
        static::query()->whereKeyNot($this->getKey())->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }
}
