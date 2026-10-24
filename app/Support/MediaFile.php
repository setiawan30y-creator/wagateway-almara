<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Berkas media yang tersimpan di disk lokal (storage/app/private/wa-media).
 * @2026-Alifiandra
 */
final class MediaFile
{
    public const DISK = 'local';

    public const LABELS = ['image' => 'gambar', 'video' => 'video', 'audio' => 'audio', 'document' => 'dokumen'];

    public function __construct(
        public readonly string $type,
        public readonly string $path,
        public readonly string $name,
        public readonly string $mime,
        public readonly int $size,
    ) {}

    public function contents(): string
    {
        return (string) Storage::disk(self::DISK)->get($this->path);
    }

    public function base64(): string
    {
        return base64_encode($this->contents());
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    public static function typeFromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }
}
