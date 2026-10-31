<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\MediaStorage;
use App\Services\MessageService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Form kirim pesan WhatsApp (satu atau beberapa nomor).
 * @2026-Alifiandra
 */
class SendController extends Controller
{
    /** Batas nomor per sekali kirim dari form, demi keamanan & kepatuhan (bukan alat broadcast massal). */
    public const MAX_RECIPIENTS = 20;

    public function create()
    {
        return view('send', [
            'devices' => Device::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->get(),
            'max' => self::MAX_RECIPIENTS,
        ]);
    }

    public function store(Request $request, MessageService $service)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', \App\Support\Tenancy::exists('devices')],
            'to' => ['required', 'string', 'max:2000'],
            'message' => ['required_without:file', 'nullable', 'string', 'max:'.config('whatsapp.max_text_length')],
            'file' => ['nullable', 'file', 'mimes:'.MediaStorage::EXTENSIONS, 'max:'.MediaStorage::MAX_KB],
        ], ['message.required_without' => 'Isi pesan atau lampiran wajib diisi.'], ['to' => 'nomor tujuan', 'message' => 'isi pesan', 'device_id' => 'perangkat', 'file' => 'lampiran']);

        $device = Device::where('is_active', true)->findOrFail($data['device_id']);

        $numbers = collect(preg_split('/[\s,;]+/', $data['to']))
            ->map(fn ($n) => PhoneNumber::normalize($n))
            ->filter()
            ->unique()
            ->values();

        $invalid = $numbers->reject(fn ($n) => PhoneNumber::isValid($n));

        if ($numbers->isEmpty() || $invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'to' => $invalid->isNotEmpty() ? 'Nomor tidak valid: '.$invalid->implode(', ') : 'Isi minimal satu nomor tujuan.',
            ]);
        }

        if ($numbers->count() > self::MAX_RECIPIENTS) {
            throw ValidationException::withMessages(['to' => 'Maksimal '.self::MAX_RECIPIENTS.' nomor per pengiriman.']);
        }

        $media = $request->hasFile('file') ? app(MediaStorage::class)->storeUpload($request->file('file')) : null;
        $messages = $numbers->map(fn ($n) => $service->queueText($device, $n, (string) ($data['message'] ?? ''), $request->user(), options: ['media' => $media]));

        $sent = $messages->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $failed = $messages->where('status', 'failed')->count();
        $queued = $messages->where('status', 'queued')->count();

        $summary = "Diproses {$messages->count()} pesan — terkirim: {$sent}, antre: {$queued}, gagal: {$failed}.";

        return redirect()->route('messages.index')->with($failed ? 'warning' : 'success', $summary);
    }
}
