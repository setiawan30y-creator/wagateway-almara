@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Perangkat')
@section('crumb', 'Perangkat')
@section('actions')
    <a class="btn" href="{{ route('devices.create') }}">+ Manual (Meta API / lainnya)</a>
    <details style="position:relative" @if ($devices->isEmpty()) open @endif>
        <summary class="btn btn-primary">@include('partials.icon', ['n' => 'device']) Tambah nomor — Scan QR</summary>
        <div class="card" style="position:absolute;right:0;top:32px;z-index:15;width:300px">
            <form class="card-b" method="POST" action="{{ route('devices.scan') }}">
                @csrf
                <div class="field"><label for="scan-name">Nama perangkat</label>
                    <input class="input" id="scan-name" name="name" placeholder="Contoh: WA Customer Service" required maxlength="100" autofocus></div>
                <button class="btn btn-primary" data-once>Lanjut ke scan QR</button>
                <div class="hint">Siapkan HP: WhatsApp → Perangkat tertaut → Tautkan perangkat.</div>
            </form>
        </div>
    </details>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="dense">
            <thead><tr><th>Nama</th><th>Provider</th><th class="hide-sm">Nomor</th><th>Status</th><th class="hide-sm">Terakhir aktif</th><th class="hide-sm right">Pesan</th><th class="right">Aksi</th></tr></thead>
            <tbody>
            @forelse ($devices as $d)
                <tr>
                    <td>
                        {{ $d->name }}
                        @if ($d->is_default)<span class="tag">utama</span>@endif
                        @unless ($d->is_active)<span class="tag">nonaktif</span>@endunless
                    </td>
                    <td>{{ $d->providerLabel() }}</td>
                    <td class="hide-sm mono">{{ $d->phone_number ?? '—' }}</td>
                    <td><span class="badge st-{{ $d->status }}">{{ $d->status }}</span>
                        @php $dq = \App\Support\SendingQuota::summary($d); @endphp
                        @if ($dq)<div class="quota {{ $dq['remaining'] === 0 ? 'full' : '' }}" title="Broadcast hari ini / batas harian{{ $dq['warmup'] ? ' (pemanasan nomor baru)' : '' }}"><span class="bar"><i style="width:{{ min(100, round($dq['sent'] * 100 / max(1, $dq['limit']))) }}%"></i></span>{{ $dq['sent'] }}/{{ $dq['limit'] }}{{ $dq['warmup'] ? ' · warm-up hari '.$dq['age'] : '' }}</div>@endif
                    </td>
                    @php $tok = $tokens[$d->id] ?? null; $plain = $tok?->plainKey(); @endphp
                    <td class="hide-sm muted">{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    <td class="hide-sm right">{{ number_format($d->messages_count, 0, ',', '.') }}</td>
                    <td class="right td-actions">
                        @if ($plain)
                            <code id="tok-{{ $d->id }}" hidden>{{ $plain }}</code>
                            <button type="button" class="btn btn-sm btn-primary" data-copy="#tok-{{ $d->id }}" title="Salin token API perangkat ini untuk aplikasi lain (billing, kasir, toko online)">🔑 Salin token</button>
                            <button type="button" class="btn btn-sm" data-toggle-row="#integ-{{ $d->id }}" aria-expanded="{{ session('open_device') == $d->id ? 'true' : 'false' }}" title="Token, URL API & contoh untuk aplikasi lain">🔗 Integrasi</button>
                        @endif
                        <form method="POST" action="{{ route('devices.check', $d) }}" class="inline">@csrf<button class="btn btn-sm" data-once>Cek</button></form>
                        @if ($d->provider === 'qr')
                            <a class="btn btn-sm {{ $d->status === 'connected' ? '' : 'btn-primary' }}" href="{{ route('devices.pair', $d) }}">{{ $d->status === 'connected' ? 'QR' : 'Scan QR' }}</a>
                            @if ($d->status === 'connected')
                                <form method="POST" action="{{ route('contacts.sync', ['device' => $d->id]) }}" class="inline">@csrf<button class="btn btn-sm" data-once title="Ambil kontak dari HP ke menu Kontak">Sinkron kontak</button></form>
                                <form method="POST" action="{{ route('groups.sync', ['device' => $d->id]) }}" class="inline">@csrf<button class="btn btn-sm" data-once title="Ambil daftar grup & anggota">Sinkron grup</button></form>
                                <form method="POST" action="{{ route('devices.logout', $d) }}" class="inline" data-confirm="Keluarkan {{ $d->name }} dari WhatsApp? Anda perlu scan QR lagi untuk menghubungkan.">@csrf<button class="btn btn-sm">Logout</button></form>
                            @endif
                        @endif
                        @unless ($d->is_default)
                            <form method="POST" action="{{ route('devices.default', $d) }}" class="inline">@csrf<button class="btn btn-sm">Jadikan utama</button></form>
                        @endunless
                        <a class="btn btn-sm" href="{{ route('devices.edit', $d) }}">Ubah</a>
                        <form method="POST" action="{{ route('devices.destroy', $d) }}" class="inline" data-confirm="Hapus perangkat {{ $d->name }}? Riwayat pesan tetap disimpan.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                @if ($plain)
                <tr class="integ-row" id="integ-{{ $d->id }}" @if (session('open_device') != $d->id) hidden @endif>
                    <td colspan="7">
                        <div class="integ">
                            <div class="integ-h"><b>Integrasi aplikasi lain — {{ $d->name }}</b>
                                <span class="muted">Pesan yang dikirim dengan token ini otomatis keluar dari nomor <b>{{ $d->phone_number ?? $d->name }}</b>.@if ($d->provider === 'log') <span class="warn-text">Perangkat Simulasi — pesan tidak benar-benar terkirim ke WhatsApp.</span>@endif</span></div>
                            <div class="integ-grid">
                                @php
                                    $rows = [
                                        ['Token (Authorization)', $plain, 'Tempel di kolom "Token" / "API Key" aplikasi Anda.'],
                                        ['URL gaya Fonnte', url('/api/send'), 'POST · field: target, message · header Authorization: token'],
                                        ['URL gaya Wablas', url('/api/send-message'), 'POST/GET · field: phone, message · header Authorization: token'],
                                        ['URL REST (lengkap)', url('/api/v1/messages/send'), 'POST JSON · to, message · header Authorization: Bearer token'],
                                        ['Kirim lewat link (GET)', url('/api/send-message').'?token='.$plain.'&phone=08xxxxxxxxxx&message=Halo', 'Ganti nomor & pesan, buka di browser untuk uji cepat.'],
                                        ['Device ID', (string) $d->id, 'Opsional — tidak perlu diisi bila memakai token perangkat ini.'],
                                    ];
                                @endphp
                                @foreach ($rows as $i => [$label, $value, $hint])
                                    <div class="integ-item">
                                        <div class="integ-label">{{ $label }}</div>
                                        <div class="secret"><code id="integ-{{ $d->id }}-{{ $i }}" @if ($i === 0 || $i === 4) class="is-masked" @endif>{{ $value }}</code>
                                            @if ($i === 0 || $i === 4)<button type="button" class="btn btn-sm btn-icon" data-reveal="#integ-{{ $d->id }}-{{ $i }}" title="Lihat / sembunyikan">👁</button>@endif
                                            <button type="button" class="btn btn-sm" data-copy="#integ-{{ $d->id }}-{{ $i }}">Salin</button></div>
                                        <div class="hint">{{ $hint }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="integ-label" style="margin-top:8px">Contoh cURL</div>
                            <div class="secret"><code id="integ-{{ $d->id }}-curl" class="is-masked" style="white-space:pre">curl -X POST {{ url('/api/send') }} \
  -H "Authorization: {{ $plain }}" \
  -d "target=08xxxxxxxxxx" -d "message=Halo dari aplikasi"</code>
                                <button type="button" class="btn btn-sm btn-icon" data-reveal="#integ-{{ $d->id }}-curl" title="Lihat / sembunyikan">👁</button>
                                <button type="button" class="btn btn-sm" data-copy="#integ-{{ $d->id }}-curl">Salin</button></div>
                            <div class="integ-foot">
                                <form method="POST" action="{{ route('devices.token', $d) }}" class="inline" data-confirm="Buat ulang token {{ $d->name }}? Token lama langsung tidak berlaku — aplikasi yang memakainya harus diperbarui.">@csrf<button class="btn btn-sm">↻ Buat ulang token</button></form>
                                <span class="hint">Token dipakai: {{ $tok->last_used_at?->diffForHumans() ?? 'belum pernah' }} · Kelola semua key di <a href="{{ route('api-keys.index') }}">API Key</a> · Respons berisi <span class="mono">status:true</span> bila pesan masuk antrean.</span>
                            </div>
                        </div>
                    </td>
                </tr>
                @endif
            @empty
                <tr><td colspan="7" class="empty">Belum ada perangkat. Klik <b>Tambah nomor — Scan QR</b> untuk menghubungkan nomor WhatsApp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
