<?php

namespace App\WhatsApp;

use App\Models\Device;
use App\WhatsApp\Contracts\WhatsAppProvider;
use InvalidArgumentException;

/**
 * Memilih adapter provider berdasarkan kolom devices.provider.
 * @2026-Alifiandra
 */
class ProviderManager
{
    public function for(Device $device): WhatsAppProvider
    {
        return $this->make($device->provider);
    }

    public function make(string $provider): WhatsAppProvider
    {
        $class = config("whatsapp.providers.{$provider}.class");

        if (! $class || ! class_exists($class)) {
            throw new InvalidArgumentException("Provider WhatsApp tidak dikenal: {$provider}");
        }

        return app($class);
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect(config('whatsapp.providers'))->map(fn ($p) => $p['label'])->all();
    }

    public function credentialFields(string $provider): array
    {
        $class = config("whatsapp.providers.{$provider}.class");

        return $class ? $class::credentialFields() : [];
    }
}
