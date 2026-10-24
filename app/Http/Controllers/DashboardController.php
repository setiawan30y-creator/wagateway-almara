<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Device;
use App\Support\Analytics;

/**
 * Dashboard ringkas + analitik 7 hari.
 * @2026-Alifiandra
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('dashboard', [
            'a' => Analytics::summary(7),
            'devices' => Device::orderByDesc('is_default')->orderBy('id')->get(),
            'conversations' => Conversation::with('contact')->where('status', '!=', 'closed')->orderByDesc('last_message_at')->limit(8)->get(),
            'campaigns' => Campaign::whereIn('status', ['running', 'scheduled', 'paused'])->latest('id')->limit(5)->get(),
        ]);
    }
}
