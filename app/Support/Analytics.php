<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use Illuminate\Support\Carbon;

/**
 * Ringkasan analitik untuk dashboard & API.
 * @2026-Alifiandra
 */
final class Analytics
{
    public static function summary(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $msgs = Message::where('created_at', '>=', $from);

        $byStatus = (clone $msgs)->where('direction', 'out')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $outTotal = (int) $byStatus->sum();
        $delivered = (int) (($byStatus['delivered'] ?? 0) + ($byStatus['read'] ?? 0));

        $daily = collect(range($days - 1, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->format('Y-m-d') => ['in' => 0, 'out' => 0]])->all();
        (clone $msgs)->get(['direction', 'created_at'])->each(function ($m) use (&$daily) {
            $d = Carbon::parse($m->created_at)->format('Y-m-d');
            if (isset($daily[$d])) {
                $daily[$d][$m->direction === 'in' ? 'in' : 'out']++;
            }
        });

        return [
            'period' => ['days' => $days, 'from' => $from->toIso8601String(), 'to' => now()->toIso8601String()],
            'messages' => [
                'incoming' => (clone $msgs)->where('direction', 'in')->count(),
                'outgoing' => $outTotal,
                'sent' => (int) (($byStatus['sent'] ?? 0) + $delivered),
                'delivered' => $delivered,
                'read' => (int) ($byStatus['read'] ?? 0),
                'failed' => (int) ($byStatus['failed'] ?? 0),
                'queued' => (int) ($byStatus['queued'] ?? 0),
                'failed_rate' => $outTotal ? round(($byStatus['failed'] ?? 0) * 100 / $outTotal, 1) : 0,
            ],
            'contacts' => [
                'total' => Contact::count(),
                'new' => Contact::where('created_at', '>=', $from)->count(),
                'opted_in' => Contact::where('consent', 'opted_in')->count(),
                'opted_out' => Contact::where('consent', 'opted_out')->count(),
            ],
            'conversations' => [
                'open' => Conversation::where('status', 'open')->count(),
                'pending' => Conversation::where('status', 'pending')->count(),
                'unread' => Conversation::where('unread_count', '>', 0)->count(),
            ],
            'campaigns' => [
                'running' => Campaign::where('status', 'running')->count(),
                'scheduled' => Campaign::where('status', 'scheduled')->count(),
                'completed' => Campaign::where('status', 'completed')->where('finished_at', '>=', $from)->count(),
            ],
            'devices' => [
                'total' => Device::count(),
                'connected' => Device::where('status', 'connected')->count(),
            ],
            'daily' => collect($daily)->map(fn ($v, $d) => ['date' => $d] + $v)->values()->all(),
        ];
    }
}
