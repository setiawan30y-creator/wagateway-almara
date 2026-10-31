@extends('layouts.app')
{{-- Lisensi & paket — @2026-Alifiandra --}}
@section('title', 'Lisensi')
@section('crumb', 'Lisensi')

@section('content')
@php
    $l = $license; $lim = $l['limits'];
    $fmt = fn ($v) => $v === null ? 'Tanpa batas' : number_format($v, 0, ',', '.');
    $yn = fn ($v) => $v ? '<span class="yes">✓</span>' : '<span class="no">—</span>';
@endphp
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Paket aktif</div>
        <div class="card-b">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                <span class="plan-chip plan-{{ $l['plan'] }}" style="font-size:13px;padding:3px 12px;margin:0">{{ $l['label'] }}</span>
                @if ($l['licensee'])<span>untuk <b>{{ $l['licensee'] }}</b></span>@endif
            </div>
            <dl class="kv">
                <dt>ID lisensi</dt><dd class="mono">{{ $l['id'] ?? '—' }}</dd>
                <dt>Berlaku sampai</dt><dd>{{ $l['plan'] === 'free' ? '—' : ($l['expires'] ? $l['expires']->format('d/m/Y') : 'Selamanya') }}</dd>
                <dt>Nomor WhatsApp</dt><dd>{{ $usage['devices'] }} / {{ $fmt($lim['devices']) }}</dd>
                <dt>Pesan keluar hari ini</dt><dd>{{ number_format($usage['messages_today'], 0, ',', '.') }} / {{ $fmt($lim['daily_messages']) }}</dd>
                <dt>Broadcast</dt><dd>{!! $yn($lim['broadcast']) !!}</dd>
                <dt>AI Agent</dt><dd>{!! $yn($lim['ai']) !!}</dd>
                <dt>Watermark di pesan</dt><dd>{{ $lim['watermark'] ? 'Ya — “'.trim(config('license.watermark_text'), "\n _").'”' : 'Tidak' }}</dd>
            </dl>
            @if ($l['plan'] !== 'free')
                <form method="POST" action="{{ route('license.remove') }}" data-confirm="Hapus lisensi? Aplikasi kembali ke paket Gratis." style="margin-top:10px">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus lisensi</button></form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-h">Aktifkan / perpanjang lisensi</div>
        <div class="card-b">
            @unless ($hasPublicKey)
                <div class="alert alert-warning">Aplikasi ini belum memiliki kunci publik lisensi, sehingga kode apa pun belum bisa diperiksa. Pemilik aplikasi: jalankan <span class="mono">php artisan license:keygen</span>.</div>
            @endunless
            <form method="POST" action="{{ route('license.activate') }}">
                @csrf
                <div class="field"><label for="key">Kode lisensi</label>
                    <textarea class="input lic-key" id="key" name="key" rows="5" placeholder="Tempel kode lisensi dari penyedia aplikasi…" required>{{ old('key') }}</textarea>
                    @error('key')<div class="error-text">{{ $message }}</div>@enderror</div>
                <button class="btn btn-primary" data-once>Aktifkan</button>
            </form>
            <div class="hint" style="margin-top:8px">{{ config('license.contact') }}</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">Perbandingan paket</div>
    <div class="table-wrap"><table class="dense plan-table">
        <thead><tr><th>Fitur</th>@foreach ($plans as $k => $p)<th class="{{ $k === $l['plan'] ? 'cur' : '' }}">{{ $p['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            <tr><td>Nomor WhatsApp</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{{ $fmt($p['devices']) }}{{ $k !== 'free' ? '*' : '' }}</td>@endforeach</tr>
            <tr><td>Pesan keluar / hari</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{{ $fmt($p['daily_messages']) }}</td>@endforeach</tr>
            <tr><td>Inbox, kontak, tiket, auto-reply, grup, API</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{!! $yn(true) !!}</td>@endforeach</tr>
            <tr><td>Broadcast & rombongan</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{!! $yn($p['broadcast']) !!}</td>@endforeach</tr>
            <tr><td>AI Agent</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{!! $yn($p['ai']) !!}</td>@endforeach</tr>
            <tr><td>Tanpa watermark</td>@foreach ($plans as $k => $p)<td class="{{ $k === $l['plan'] ? 'cur' : '' }}">{!! $yn(! $p['watermark']) !!}</td>@endforeach</tr>
        </tbody>
    </table></div>
    <div class="card-b hint">* Jumlah nomor paket Pro mengikuti kode lisensi masing-masing.</div>
</div>
@endsection
