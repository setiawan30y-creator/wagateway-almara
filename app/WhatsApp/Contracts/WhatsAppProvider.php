<?php

namespace App\WhatsApp\Contracts;

use App\Models\Device;
use App\Support\MediaFile;
use App\WhatsApp\ConnectionStatus;
use App\WhatsApp\SendResult;

/**
 * Kontrak adapter provider WhatsApp.
 * Logika provider dipisah dari controller bisnis (lihat blueprint bagian C & F).
 * @2026-Alifiandra
 */
interface WhatsAppProvider
{
    /** Kirim pesan teks ke nomor tujuan (format internasional tanpa '+', mis. 6281234567890). */
    public function sendText(Device $device, string $to, string $body): SendResult;

    /** Kirim media (gambar / video / audio / dokumen) dengan keterangan opsional. */
    public function sendMedia(Device $device, string $to, MediaFile $media, ?string $caption = null): SendResult;

    /** Cek status koneksi perangkat. */
    public function status(Device $device): ConnectionStatus;

    /** Field kredensial yang dibutuhkan provider: key => [label, type, required, placeholder]. */
    public static function credentialFields(): array;
}
