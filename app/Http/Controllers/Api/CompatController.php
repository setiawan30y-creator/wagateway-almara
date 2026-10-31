<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Device;
use App\Services\MessageService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * Endpoint kompatibel untuk aplikasi lain (billing, kasir, dll.) yang sudah mendukung
 * gateway populer — cukup ganti alamat & token:
 *   Gaya Fonnte : POST {APP_URL}/api/send          Authorization: <api key>   target, message, url|file (lampiran opsional)
 *   Gaya Wablas : POST/GET {APP_URL}/api/send-message  Authorization: <api key>   phone, message
 * Token juga boleh lewat parameter "token". @2026-Alifiandra
 */
class CompatController extends Controller
{
    /** Gaya Fonnte: target boleh banyak nomor dipisah koma. */
    public function fonnte(Request $request, MessageService $service, \App\Services\MediaStorage $storage)
    {
        $targets = $this->numbers((string) ($request->input('target') ?? $request->input('phone') ?? ''), (string) $request->input('countryCode', ''));
        $text = (string) ($request->input('message') ?? $request->input('text') ?? '');
        $hasMedia = $request->hasFile('file') || filled($request->input('url'));

        if (! $targets || (trim($text) === '' && ! $hasMedia)) {
            return response()->json(['status' => false, 'reason' => 'target dan message wajib diisi'], 422);
        }

        $device = $this->device($request);
        if (! $device) {
            return response()->json(['status' => false, 'reason' => 'perangkat WhatsApp belum terhubung'], 422);
        }

        // Lampiran gaya Fonnte: "url" (gambar/berkas publik, mis. QRIS) atau upload "file"; message jadi caption.
        $media = null;
        if ($hasMedia) {
            $media = $this->media($request, $storage);
            if (is_string($media)) {
                return response()->json(['status' => false, 'reason' => $media], 422);
            }
        }

        $ids = [];
        foreach ($targets as $to) {
            $ids[] = $service->queueText($device, $to, $text, $this->key($request)->user, $this->key($request), null, $media ? ['media' => $media] : [])->public_id;
        }

        return response()->json(['status' => true, 'detail' => 'success! message in queue', 'id' => $ids, 'target' => $targets, 'process' => 'pending']);
    }

    /** Gaya Wablas: satu nomor (phone), atau beberapa dipisah koma. */
    public function wablas(Request $request, MessageService $service)
    {
        $targets = $this->numbers((string) ($request->input('phone') ?? $request->input('target') ?? $request->input('to') ?? ''));
        $text = (string) ($request->input('message') ?? $request->input('text') ?? '');

        if (! $targets || trim($text) === '') {
            return response()->json(['status' => false, 'message' => 'phone dan message wajib diisi'], 422);
        }

        $device = $this->device($request);
        if (! $device) {
            return response()->json(['status' => false, 'message' => 'perangkat WhatsApp belum terhubung'], 422);
        }

        $messages = [];
        foreach ($targets as $to) {
            $m = $service->queueText($device, $to, $text, $this->key($request)->user, $this->key($request));
            $messages[] = ['id' => $m->public_id, 'phone' => $m->to, 'message' => $text, 'status' => $m->status];
        }

        return response()->json(['status' => true, 'message' => 'Message is pending and waiting to be processed', 'data' => ['device_id' => $device->id, 'quota' => null, 'messages' => $messages]]);
    }

    private function key(Request $request): ApiKey
    {
        return $request->attributes->get('api_key');
    }

    private function device(Request $request): ?Device
    {
        return $this->key($request)->resolveDevice($request->integer('device_id') ?: null);
    }

    /** @return \App\Support\MediaFile|string lampiran, atau pesan galat */
    private function media(Request $request, \App\Services\MediaStorage $storage)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (! $file->isValid() || ! in_array($ext, explode(',', \App\Services\MediaStorage::EXTENSIONS), true) || $file->getSize() > \App\Services\MediaStorage::MAX_KB * 1024) {
                return 'file tidak didukung atau lebih dari 16 MB';
            }

            return $storage->storeUpload($file);
        }

        $url = (string) $request->input('url');
        if (! preg_match('#^https?://#i', $url)) {
            return 'url lampiran tidak valid';
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 3]])->get($url);
        } catch (\Throwable) {
            return 'gagal mengunduh lampiran dari url';
        }

        $body = $res->body();
        $mime = strtolower(trim(explode(';', (string) $res->header('Content-Type'))[0]));
        if (! $res->successful() || $body === '' || strlen($body) > \App\Services\MediaStorage::MAX_KB * 1024) {
            return 'gagal mengunduh lampiran dari url';
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: 'application/octet-stream';
        }
        if (str_starts_with($mime, 'text/html')) {
            return 'url bukan gambar / berkas';
        }

        return $storage->storeBinary($body, $mime, basename(parse_url($url, PHP_URL_PATH) ?: '') ?: null);
    }

    /** "0812…, 0813…" -> nomor valid (maks 100). */
    private function numbers(string $raw, string $cc = ''): array
    {
        return collect(preg_split('/[,;\s]+/', $raw) ?: [])
            ->map(fn ($n) => PhoneNumber::normalize(trim(explode('|', $n)[0]), ($c = preg_replace('/\D/', '', $cc)) !== '' && $c !== '0' ? $c : null))
            ->filter(fn ($n) => PhoneNumber::isValid($n))
            ->unique()->take(100)->values()->all();
    }
}
