@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Campaign Baru')
@section('crumb', 'Broadcast / Baru')

@section('content')
@if ($templates->isEmpty() || $devices->isEmpty())
    <div class="alert alert-warning">Buat minimal satu <a href="{{ route('templates.create') }}">template</a> dan satu <a href="{{ route('devices.create') }}">perangkat aktif</a> terlebih dahulu.</div>
@endif
<form method="POST" action="{{ route('campaigns.store') }}" data-audience-form="{{ route('campaigns.preview') }}">
@csrf
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">1. Campaign</div>
        <div class="card-b">
            <div class="field"><label for="name">Nama campaign</label><input class="input" id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Promo akhir bulan" required maxlength="150"></div>
            <div class="field"><label for="template_id">Template</label>
                <select class="input" id="template_id" name="template_id" required>
                    @foreach ($templates as $t)<option value="{{ $t->id }}" @selected(old('template_id') == $t->id)>{{ $t->name }} ({{ $t->variants->count() }} varian)</option>@endforeach
                </select></div>
            <div class="field"><label for="device_id">Kirim melalui</label>
                <select class="input" id="device_id" name="device_id" required>
                    @foreach ($devices as $d)<option value="{{ $d->id }}" @selected(old('device_id') == $d->id)>{{ $d->name }} — {{ $d->providerLabel() }}</option>@endforeach
                </select></div>
            <div class="field"><label for="rate_per_minute">Kecepatan (pesan / menit)</label>
                <input class="input" id="rate_per_minute" type="number" name="rate_per_minute" min="1" max="120" value="{{ old('rate_per_minute', $campaign->rate_per_minute) }}" required>
                <div class="hint">Rate limiter. Kecepatan rendah lebih aman untuk kualitas nomor.</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">2. Audience</div>
        <div class="card-b">
            <div class="field"><label>Tag (kosong = semua)</label>
                <div class="checks">@forelse ($tags as $t)<label><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked(in_array($t->id, old('tags', [])))> {{ $t->name }} <span class="muted">({{ $t->contacts_count }})</span></label>@empty<span class="muted">Belum ada tag.</span>@endforelse</div></div>
            <div class="field"><label>Tahap pipeline (kosong = semua)</label>
                <div class="checks">@foreach (\App\Models\Contact::STAGES as $k => $v)<label><input type="checkbox" name="stages[]" value="{{ $k }}" @checked(in_array($k, old('stages', [])))> {{ $v }}</label>@endforeach</div></div>
            <div class="alert alert-success" data-audience-out style="margin:0">Menghitung…</div>
            <div class="hint">Hanya kontak <b>opt-in</b> yang dikirimi. Kontak opt-out / belum ada izin otomatis dilewati. Frequency cap, jam tenang & auto-pause mengikuti <a href="{{ route('settings.edit') }}">Pengaturan</a>.</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">3. Kirim</div>
    <div class="card-b">
        <div class="checks" style="margin-bottom:8px">
            <label><input type="radio" name="mode" value="draft" @checked(old('mode', 'draft') === 'draft')> Simpan sebagai draft</label>
            <label><input type="radio" name="mode" value="now" @checked(old('mode') === 'now')> Mulai sekarang</label>
            <label><input type="radio" name="mode" value="schedule" @checked(old('mode') === 'schedule')> Jadwalkan</label>
            <input class="input" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" style="width:auto;height:28px">
        </div>
        <button class="btn btn-primary" data-once @disabled($templates->isEmpty() || $devices->isEmpty())>Simpan campaign</button>
        <a class="btn" href="{{ route('campaigns.index') }}">Batal</a>
        <div class="hint" style="margin-top:6px">Pengiriman berjalan bertahap tiap menit lewat scheduler (<span class="mono">php artisan schedule:work</span>). Tombol “Proses batch” di halaman campaign bisa dipakai untuk memicu manual.</div>
    </div>
</div>
</form>
@endsection
