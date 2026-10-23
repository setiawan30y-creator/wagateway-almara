<?php

namespace App\WhatsApp;

/**
 * Status koneksi perangkat.
 * @2026-Alifiandra
 */
final class ConnectionStatus
{
    public function __construct(
        public readonly bool $connected,
        public readonly string $message = '',
        public readonly ?string $qr = null,
    ) {}
}
