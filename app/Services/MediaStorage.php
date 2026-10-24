<?php

namespace App\Services;

use App\Support\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simpan berkas media (upload panel/API atau media masuk dari provider).
 * Disimpan privat — hanya bisa diakses lewat route yang memerlukan login.
 * @2026-Alifiandra
 */
class MediaStorage
{
    /** Ekstensi yang diizinkan (sesuai dukungan WhatsApp). */
    public const EXTENSIONS = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,mp3,ogg,m4a,aac,amr,opus,mp4,3gp';

    public const MAX_KB = 16384; // 16 MB

    public function storeUpload(UploadedFile $file): MediaFile
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs($this->dir(), Str::lower((string) Str::ulid()).'.'.$ext, MediaFile::DISK);

        return new MediaFile(MediaFile::typeFromMime($mime), $path, $this->cleanName($file->getClientOriginalName(), $ext), $mime, (int) $file->getSize());
    }

    public function storeBinary(string $data, string $mime, ?string $name = null): MediaFile
    {
        $ext = $this->extFromMime($mime);
        $path = $this->dir().'/'.Str::lower((string) Str::ulid()).'.'.$ext;
        Storage::disk(MediaFile::DISK)->put($path, $data);

        return new MediaFile(MediaFile::typeFromMime($mime), $path, $this->cleanName($name ?: 'media.'.$ext, $ext), $mime, strlen($data));
    }

    private function dir(): string
    {
        return 'wa-media/'.now()->format('Y/m');
    }

    private function cleanName(string $name, string $ext): string
    {
        $name = trim(preg_replace('/[^\w.\- ]+/u', '_', $name) ?? '', ' ._');

        return mb_substr($name !== '' ? $name : 'berkas.'.$ext, 0, 150);
    }

    private function extFromMime(string $mime): string
    {
        return [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'video/mp4' => 'mp4', 'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg', 'audio/ogg; codecs=opus' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/amr' => 'amr',
            'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/csv' => 'csv', 'application/zip' => 'zip',
            'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ][strtolower(trim($mime))] ?? (str_contains($mime, 'ogg') ? 'ogg' : 'bin');
    }
}
