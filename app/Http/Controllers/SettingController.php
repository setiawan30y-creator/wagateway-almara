<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengaturan gateway & Smart Broadcast Protection. @2026-Alifiandra */
class SettingController extends Controller
{
    public function edit()
    {
        $values = collect(Setting::DEFAULTS)->map(fn ($v, $k) => Setting::get($k))->all();

        return view('settings', ['s' => $values]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'quiet_start' => ['required', 'date_format:H:i'],
            'quiet_end' => ['required', 'date_format:H:i'],
            'frequency_cap_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'default_rate_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'max_recipients_per_campaign' => ['required', 'integer', 'min:1', 'max:100000'],
            'auto_pause_failed_rate' => ['required', 'numeric', 'min:1', 'max:100'],
            'auto_pause_optout_rate' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'auto_pause_min_sample' => ['required', 'integer', 'min:5', 'max:10000'],
            'stop_keywords' => ['required', 'string', 'max:255'],
            'start_keywords' => ['required', 'string', 'max:255'],
            'stop_reply' => ['nullable', 'string', 'max:1000'],
            'start_reply' => ['nullable', 'string', 'max:1000'],
            'auto_assign' => ['required', Rule::in(['none', 'round_robin'])],
            'sla_urgent_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'sla_high_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'sla_normal_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'sla_low_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['integer', 'between:1,7'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
        ], [], ['work_end' => 'jam selesai kerja']);

        $data['work_days'] = implode(',', $data['work_days'] ?? []);

        $data['quiet_hours_enabled'] = $request->boolean('quiet_hours_enabled') ? '1' : '0';

        foreach ($data as $key => $value) {
            Setting::set($key, (string) $value);
        }

        AuditLog::record('settings.update', 'Ubah pengaturan gateway');

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
