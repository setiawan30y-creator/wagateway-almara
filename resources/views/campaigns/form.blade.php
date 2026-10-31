@extends('layouts.app')
{{-- Campaign broadcast baru / duplikat — @2026-Alifiandra --}}
@section('title', $from ? 'Duplikat Campaign' : 'Campaign Baru')
@section('crumb', 'Broadcast / '.($from ? 'Duplikat' : 'Baru'))

@section('content')
@if ($templates->isEmpty() || $devices->isEmpty())
    <div class="alert alert-warning">Buat minimal satu <a href="{{ route('templates.create') }}">template</a> dan satu <a href="{{ route('devices.create') }}">perangkat aktif</a> terlebih dahulu.</div>
@endif
@if ($from)
    <div class="alert alert-success">Menyalin campaign <b>{{ $from->name }}</b> — ganti rombongan / tag / jadwal sesuai peruntukan baru, lalu simpan.</div>
@endif
@php
    $aud = $campaign->audience ?? [];
    $selTemplates = array_map('intval', old('template_ids', $campaign->templateIds() ?: ($templates->count() === 1 ? [$templates->first()->id] : [])));
    $selLists = array_map('intval', old('lists', $aud['lists'] ?? []));
    $selTags = array_map('intval', old('tags', $aud['tags'] ?? []));
    $selStages = old('stages', $aud['stages'] ?? []);
@endphp
<form method="POST" action="{{ route('campaigns.store') }}" enctype="multipart/form-data" data-audience-form="{{ route('campaigns.preview') }}">
@csrf
@if ($from)<input type="hidden" name="copy_media_from" value="{{ $from->media_type ? $from->id : '' }}">@endif
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">1. Campaign</div>
        <div class="card-b">
            <div class="field"><label for="name">Nama campaign</label><input class="input" id="name" name="name" value="{{ old('name', $campaign->name) }}" placeholder="Contoh: Promo akhir bulan" required maxlength="150"></div>
            <div class="field"><label for="device_id">Kirim melalui</label>
                <select class="input" id="device_id" name="device_id" required>
                    @foreach ($devices as $d)<option value="{{ $d->id }}" @selected(old('device_id', $campaign->device_id) == $d->id)>{{ $d->name }} — {{ $d->providerLabel() }}</option>@endforeach
                </select></div>
            <div class="field"><label for="rate_per_minute">Kecepatan (pesan / menit)</label>
                <input class="input" id="rate_per_minute" type="number" name="rate_per_minute" min="1" max="120" value="{{ old('rate_per_minute', $campaign->rate_per_minute) }}" required>
                <div class="hint">Rate limiter. Untuk perangkat Scan QR, pesan dikirim satu per satu dengan jeda acak {{ implode('–', \App\Support\SendingQuota::delayRange()) }} detik (maks ±{{ \App\Support\SendingQuota::maxPerMinute() }}/menit) dan dibatasi kuota harian per nomor — atur di <a href="{{ route('settings.edit') }}">Pengaturan</a>.</div></div>

            <div class="field">
                <div class="label-row"><label>Template pesan</label><button type="button" class="btn btn-sm" data-template-all>Pilih semua</button></div>
                <div class="pick-list">
                    @foreach ($templates as $t)
                        <label class="pick">
                            <input type="checkbox" name="template_ids[]" value="{{ $t->id }}" data-variants="{{ max(1, $t->variants->count()) }}" @checked(in_array($t->id, $selTemplates, true))>
                            <span><b>{{ $t->name }}</b>
                                <span class="muted">{{ $t->variants->count() }} varian @if ($t->media_type)· 📎 {{ \App\Support\MediaFile::LABELS[$t->media_type] ?? 'file' }}@endif</span></span>
                        </label>
                    @endforeach
                </div>
                <div class="hint" data-template-count></div>
                @error('template_ids')<div class="error-text">{{ $message }}</div>@enderror
                <div class="hint">Pilih beberapa template agar tiap penerima mendapat pesan yang berbeda secara acak (tidak seragam). Tulis juga variasi kata di template dengan format <span class="mono">{Halo|Hai|Selamat siang}</span>.</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">2. Penerima</div>
        <div class="card-b">
            <div class="field"><div class="label-row"><label>Rombongan</label><a class="btn btn-sm" href="{{ route('lists.index') }}">Kelola rombongan</a></div>
                <div class="checks">@forelse ($lists as $l)<label><input type="checkbox" name="lists[]" value="{{ $l->id }}" @checked(in_array($l->id, $selLists, true))> {{ $l->name }} <span class="muted">({{ $l->contacts_count }})</span></label>@empty<span class="muted">Belum ada rombongan. <a href="{{ route('lists.create') }}">Buat rombongan</a> untuk mengelompokkan penerima per peruntukan.</span>@endforelse</div></div>
            <div class="field"><label>Tag (kosong = semua)</label>
                <div class="checks">@forelse ($tags as $t)<label><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked(in_array($t->id, $selTags, true))> {{ $t->name }} <span class="muted">({{ $t->contacts_count }})</span></label>@empty<span class="muted">Belum ada tag.</span>@endforelse</div></div>
            <div class="field"><label>Tahap pipeline (kosong = semua)</label>
                <div class="checks">@foreach (\App\Models\Contact::STAGES as $k => $v)<label><input type="checkbox" name="stages[]" value="{{ $k }}" @checked(in_array($k, $selStages, true))> {{ $v }}</label>@endforeach</div></div>
            <div class="alert alert-success" data-audience-out style="margin:0">Menghitung…</div>
            <div class="hint">Rombongan, tag, dan tahap saling mempersempit (kosong = tidak disaring). Hanya kontak <b>opt-in</b> yang dikirimi. Frequency cap, jam tenang & auto-pause mengikuti <a href="{{ route('settings.edit') }}">Pengaturan</a>.</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">3. Lampiran foto / file <span class="muted" style="font-weight:400;text-transform:none;letter-spacing:0">(opsional)</span></div>
    <div class="card-b">
        @if ($from && $from->media_type)
            <div class="hint" style="margin-bottom:6px">📎 Memakai lampiran campaign asal: <b>{{ $from->media_name }}</b>. Pilih berkas baru di bawah untuk menggantinya.</div>
        @endif
        @include('partials.dropzone', ['name' => 'file', 'id' => 'campaign-file'])
        <div class="hint" style="margin-top:6px">Lampiran di sini berlaku untuk semua penerima. Bila kosong, lampiran milik template yang terpilih (bila ada) yang dikirim. Isi template menjadi keterangan (caption).</div>
        @error('file')<div class="error-text">{{ $message }}</div>@enderror
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">4. Kirim</div>
    <div class="card-b">
        @php $mode = old('mode', 'now'); @endphp
        <div class="send-modes" data-send-modes>
            <label class="send-mode"><input type="radio" name="mode" value="now" @checked($mode === 'now')>
                <span><b>🚀 Kirim sekarang</b><small>Langsung mulai mengirim ke penerima.</small></span></label>
            <label class="send-mode"><input type="radio" name="mode" value="schedule" @checked($mode === 'schedule')>
                <span><b>🕒 Jadwalkan</b><small>Kirim otomatis pada waktu tertentu.</small>
                    <input class="input" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" style="margin-top:4px;height:28px"></span></label>
            <label class="send-mode"><input type="radio" name="mode" value="draft" @checked($mode === 'draft')>
                <span><b>📝 Simpan dulu (draft)</b><small>Belum dikirim. Kirim nanti dari halaman campaign → tombol “Kirim sekarang”.</small></span></label>
        </div>
        @error('scheduled_at')<div class="error-text">{{ $message }}</div>@enderror
        <div style="margin-top:10px">
            <button class="btn btn-primary" data-once data-send-button @disabled($templates->isEmpty() || $devices->isEmpty())>🚀 Kirim sekarang</button>
            <a class="btn" href="{{ route('campaigns.index') }}">Batal</a>
        </div>
        <div class="hint" style="margin-top:6px">Pesan dikirim bertahap (bukan sekaligus) — biarkan jendela <b>Scheduler</b> dan <b>Engine</b> dari <span class="mono">mulai.bat</span> tetap terbuka sampai selesai. Progres terlihat di halaman campaign.</div>
    </div>
</div>
</form>
@endsection
