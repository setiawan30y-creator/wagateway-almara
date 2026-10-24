@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Kirim Pesan')
@section('crumb', 'Kirim Pesan')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Pesan baru</div>
        <div class="card-b">
            @if ($devices->isEmpty())
                <div class="empty">Belum ada perangkat aktif. <a href="{{ route('devices.create') }}">Tambah perangkat</a> terlebih dahulu.</div>
            @else
            <form method="POST" action="{{ route('send.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label for="device_id">Perangkat pengirim</label>
                    <select class="input" id="device_id" name="device_id" required>
                        @foreach ($devices as $d)
                            <option value="{{ $d->id }}" @selected(old('device_id', $devices->first()->id) == $d->id)>
                                {{ $d->name }} — {{ $d->providerLabel() }}{{ $d->phone_number ? ' ('.$d->phone_number.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="to">Nomor tujuan</label>
                    <textarea class="input" id="to" name="to" rows="3" style="min-height:60px" maxlength="2000"
                        data-counter="#to-count" data-count-mode="numbers"
                        placeholder="Contoh: 081234567890 — pisahkan dengan koma / baris baru untuk beberapa nomor" required>{{ old('to') }}</textarea>
                    <div class="hint">Format 08xx, 628xx, atau +628xx otomatis dinormalisasi. Maksimal {{ $max }} nomor per kirim. <span id="to-count" class="mono"></span></div>
                    @error('to')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="message">Isi pesan</label>
                    <textarea class="input" id="message" name="message" rows="7" maxlength="{{ config('whatsapp.max_text_length') }}"
                        data-counter="#msg-count" placeholder="Tulis pesan di sini… (menjadi keterangan bila ada lampiran)">{{ old('message') }}</textarea>
                    <div class="counter" id="msg-count"></div>
                    @error('message')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="file">Lampiran (opsional)</label>
                    <input class="input" id="file" type="file" name="file" style="height:auto;padding:5px" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp3,.ogg,.m4a,.aac,.amr,.opus,.mp4,.3gp">
                    <div class="hint">Gambar (JPG/PNG/WEBP), dokumen (PDF/Office/TXT/CSV/ZIP), audio, atau video MP4. Maks. 16 MB.</div>
                    @error('file')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary" data-once>@include('partials.icon', ['n' => 'send']) Kirim</button>
            </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-h">Panduan singkat</div>
        <div class="card-b stack">
            <div><b>Simulasi (Log)</b> — pesan tidak benar-benar dikirim, hanya dicatat di <span class="mono">storage/logs/laravel.log</span>. Cocok untuk uji coba.</div>
            <div><b>Meta Cloud API</b> — jalur resmi. Pesan teks bebas hanya bisa dikirim ke kontak yang menghubungi Anda dalam 24 jam terakhir; di luar itu Meta mewajibkan template yang disetujui.</div>
            <div><b>QR Multi-Device</b> — memakai engine eksternal yang Anda jalankan sendiri. Hubungkan nomor lewat menu Perangkat → Pairing.</div>
            <div class="muted">Kirim hanya ke kontak yang memberi izin (opt-in) dan hormati permintaan berhenti (opt-out). Pengiriman massal tanpa izin berisiko nomor diblokir.</div>
        </div>
    </div>
</div>
@endsection
