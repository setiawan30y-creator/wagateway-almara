<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Message;
use Illuminate\Http\Request;

/**
 * Riwayat pesan.
 * @2026-Alifiandra
 */
class MessageController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');
        $deviceId = $request->query('device');

        $messages = Message::with('device')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('to', 'like', '%'.preg_replace('/\D+/', '', $q).'%')
                ->orWhere('body', 'like', '%'.$q.'%')
                ->orWhere('public_id', $q)))
            ->when(in_array($status, Message::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('messages.index', [
            'messages' => $messages,
            'devices' => Device::orderBy('name')->get(['id', 'name']),
            'statuses' => Message::STATUSES,
        ]);
    }

    public function show(Message $message)
    {
        $message->load('device', 'user', 'apiKey');

        return view('messages.show', compact('message'));
    }

    public function retry(Message $message)
    {
        abort_unless($message->status === 'failed', 422, 'Hanya pesan gagal yang bisa dikirim ulang.');

        $message->forceFill(['status' => 'queued', 'error' => null])->save();
        AuditLog::record('message.retry', 'Kirim ulang '.$message->public_id);
        SendWhatsAppMessage::dispatch($message->id);

        $message->refresh();

        return back()->with($message->status === 'failed' ? 'warning' : 'success', 'Kirim ulang: status sekarang '.$message->status.'.');
    }

    public function export(Request $request)
    {
        $filename = 'riwayat-pesan-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'waktu', 'perangkat', 'tujuan', 'status', 'pesan', 'error']);

            Message::with('device')->latest('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $m) {
                    fputcsv($out, [$m->public_id, $m->created_at, $m->device?->name, $m->to, $m->status, $m->body, $m->error]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
