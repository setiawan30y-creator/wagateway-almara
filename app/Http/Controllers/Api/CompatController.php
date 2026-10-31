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
 *   Gaya Fonnte : POST {APP_URL}/api/send          Authorization: <api key>   target, message
 *   Gaya Wablas : POST/GET {APP_URL}/api/send-message  Authorization: <api key>   phone, message
 * Token juga boleh lewat parameter "token". @2026-Alifiandra
 */
class CompatController extends Controller
{
    /** Gaya Fonnte: target boleh banyak nomor dipisah koma. */
    public function fonnte(Request $request, MessageService $service)
    {
        $targets = $this->numbers((string) ($request->input('target') ?? $request->input('phone') ?? ''), (string) $request->input('countryCode', ''));
        $text = (string) ($request->input('message') ?? $request->input('text') ?? '');

        if (! $targets || trim($text) === '') {
            return response()->json(['status' => false, 'reason' => 'target dan message wajib diisi'], 422);
        }

        $device = $this->device($request);
        if (! $device) {
            return response()->json(['status' => false, 'reason' => 'perangkat WhatsApp belum terhubung'], 422);
        }

        $ids = [];
        foreach ($targets as $to) {
            $ids[] = $service->queueText($device, $to, $text, $this->key($request)->user, $this->key($request))->public_id;
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

    /** "0812…, 0813…" -> nomor valid (maks 100). */
    private function numbers(string $raw, string $cc = ''): array
    {
        return collect(preg_split('/[,;\s]+/', $raw) ?: [])
            ->map(fn ($n) => PhoneNumber::normalize(trim(explode('|', $n)[0]), $cc !== '' ? preg_replace('/\D/', '', $cc) : null))
            ->filter(fn ($n) => PhoneNumber::isValid($n))
            ->unique()->take(100)->values()->all();
    }
}
