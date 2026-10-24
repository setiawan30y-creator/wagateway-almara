@extends('layouts.app')
{{-- Pengaturan gateway & Smart Broadcast Protection — @2026-Alifiandra --}}
@section('title', 'Pengaturan')
@section('crumb', 'Pengaturan')

@section('content')
<form method="POST" action="{{ route('settings.update') }}">
@csrf @method('PUT')
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Smart Broadcast Protection</div>
        <div class="card-b">
            <div class="field"><label class="check"><input type="checkbox" name="quiet_hours_enabled" value="1" @checked($s['quiet_hours_enabled'] === '1')> Aktifkan jam tenang (quiet hours)</label></div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label>Mulai jam tenang</label><input class="input" type="time" name="quiet_start" value="{{ $s['quiet_start'] }}" required></div>
                <div class="field"><label>Selesai jam tenang</label><input class="input" type="time" name="quiet_end" value="{{ $s['quiet_end'] }}" required></div>
                <div class="field"><label>Frequency cap (jam)</label><input class="input" type="number" name="frequency_cap_hours" min="0" max="720" value="{{ $s['frequency_cap_hours'] }}" required><div class="hint">Maks. 1 broadcast per kontak dalam rentang ini. 0 = nonaktif.</div></div>
                <div class="field"><label>Kecepatan default / menit</label><input class="input" type="number" name="default_rate_per_minute" min="1" max="120" value="{{ $s['default_rate_per_minute'] }}" required></div>
                <div class="field"><label>Maks. penerima per campaign</label><input class="input" type="number" name="max_recipients_per_campaign" min="1" value="{{ $s['max_recipients_per_campaign'] }}" required></div>
                <div class="field"><label>Sampel minimum auto-pause</label><input class="input" type="number" name="auto_pause_min_sample" min="5" value="{{ $s['auto_pause_min_sample'] }}" required></div>
                <div class="field"><label>Auto-pause bila gagal ≥ (%)</label><input class="input" type="number" step="0.5" name="auto_pause_failed_rate" min="1" max="100" value="{{ $s['auto_pause_failed_rate'] }}" required></div>
                <div class="field"><label>Auto-pause bila opt-out ≥ (%)</label><input class="input" type="number" step="0.5" name="auto_pause_optout_rate" min="0.5" max="100" value="{{ $s['auto_pause_optout_rate'] }}" required></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Opt-in / opt-out & Inbox</div>
        <div class="card-b">
            <div class="field"><label>Kata kunci berhenti (STOP)</label><input class="input" name="stop_keywords" value="{{ $s['stop_keywords'] }}" required><div class="hint">Dipisah koma. Pesan masuk yang persis sama → kontak opt-out.</div></div>
            <div class="field"><label>Balasan otomatis saat berhenti</label><textarea class="input" name="stop_reply" rows="2">{{ $s['stop_reply'] }}</textarea></div>
            <div class="field"><label>Kata kunci berlangganan (START)</label><input class="input" name="start_keywords" value="{{ $s['start_keywords'] }}" required></div>
            <div class="field"><label>Balasan otomatis saat berlangganan</label><textarea class="input" name="start_reply" rows="2">{{ $s['start_reply'] }}</textarea></div>
            <div class="field"><label>Penugasan percakapan baru</label>
                <select class="input" name="auto_assign">
                    <option value="none" @selected($s['auto_assign'] === 'none')>Manual</option>
                    <option value="round_robin" @selected($s['auto_assign'] === 'round_robin')>Otomatis bergiliran (round-robin) ke pengguna aktif</option>
                </select></div>
        </div>
    </div>
</div>
<div class="grid grid-2" style="margin-top:10px">
    <div class="card">
        <div class="card-h">SLA tiket (jam)</div>
        <div class="card-b grid grid-2" style="gap:0 10px">
            @foreach (['urgent' => 'Urgent', 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah'] as $k => $v)
                <div class="field"><label>{{ $v }}</label><input class="input" type="number" min="1" max="720" name="sla_{{ $k }}_hours" value="{{ $s['sla_'.$k.'_hours'] }}" required></div>
            @endforeach
            <div class="hint" style="grid-column:1/-1">Tiket yang melewati SLA dieskalasi otomatis (prioritas naik + notifikasi admin) oleh scheduler.</div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Jam kerja (untuk auto-reply “di luar jam kerja”)</div>
        <div class="card-b">
            @php $days = explode(',', $s['work_days']); @endphp
            <div class="checks" style="margin-bottom:8px">
                @foreach ([1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'] as $k => $v)
                    <label><input type="checkbox" name="work_days[]" value="{{ $k }}" @checked(in_array((string) $k, $days, true))> {{ $v }}</label>
                @endforeach
            </div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label>Mulai</label><input class="input" type="time" name="work_start" value="{{ $s['work_start'] }}" required></div>
                <div class="field"><label>Selesai</label><input class="input" type="time" name="work_end" value="{{ $s['work_end'] }}" required>@error('work_end')<div class="error-text">{{ $message }}</div>@enderror</div>
            </div>
        </div>
    </div>
</div>
<div style="margin-top:10px"><button class="btn btn-primary" data-once>Simpan pengaturan</button></div>
</form>
@endsection
