<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

/**
 * Menampilkan media pesan (hanya untuk pengguna yang login).
 * @2026-Alifiandra
 */
class MediaController extends Controller
{
    public function show(Message $message)
    {
        $media = $message->mediaFile();
        abort_unless($media && Storage::disk($media::DISK)->exists($media->path), 404);

        $inline = in_array($media->type, ['image', 'audio', 'video'], true) && ! request()->boolean('download');

        return response()->file($media->absolutePath(), [
            'Content-Type' => $media->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($media->name).'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
