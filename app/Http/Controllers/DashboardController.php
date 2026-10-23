<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Message;

/**
 * Dashboard ringkas.
 * @2026-Alifiandra
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Message::whereDate('created_at', today());

        $stats = [
            'today' => (clone $today)->count(),
            'sent_today' => (clone $today)->whereIn('status', ['sent', 'delivered', 'read'])->count(),
            'failed_today' => (clone $today)->where('status', 'failed')->count(),
            'queued' => Message::where('status', 'queued')->count(),
            'total' => Message::count(),
        ];

        return view('dashboard', [
            'stats' => $stats,
            'devices' => Device::orderByDesc('is_default')->orderBy('id')->get(),
            'recent' => Message::with('device')->latest('id')->limit(10)->get(),
        ]);
    }
}
