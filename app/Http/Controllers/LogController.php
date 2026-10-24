<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;

/** Log: audit, API, webhook. @2026-Alifiandra */
class LogController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['audit', 'api', 'webhook'], true) ? $request->query('tab') : 'audit';

        $rows = match ($tab) {
            'audit' => AuditLog::with('user')->latest('id')->paginate(50)->withQueryString(),
            'api' => ApiLog::with('apiKey')->latest('id')->paginate(50)->withQueryString(),
            'webhook' => WebhookDelivery::with('webhook')->latest('id')->paginate(50)->withQueryString(),
        };

        return view('logs.index', compact('tab', 'rows'));
    }
}
