<?php

namespace App\WhatsApp;

/**
 * Hasil pengiriman dari provider.
 * @2026-Alifiandra
 */
final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = false,
        public readonly bool $queued = false, // diterima engine, dikirim nanti (antrean broadcast)
    ) {}

    public static function success(?string $providerMessageId = null, bool $queued = false): self
    {
        return new self(true, $providerMessageId, null, false, $queued);
    }

    public static function failure(string $error, bool $retryable = false): self
    {
        return new self(false, null, $error, $retryable);
    }
}
