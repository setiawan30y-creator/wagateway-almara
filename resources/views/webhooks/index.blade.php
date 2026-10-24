@extends('layouts.app')
{{-- Webhook keluar — @2026-Alifiandra --}}
@section('title', 'Webhook')
@section('crumb', 'Webhook')
@section('actions')<a class="btn btn-primary" href="{{ route('webhooks.create') }}">+ Webhook</a>@endsection

@section('content')
@if (session('plain_secret'))
    <div class="card" style="margin-bottom:10px;border-color:var(--warn)">
        <div class="card-h" style="color:var(--warn)">Signing secret — salin sekarang</div>
        <div class="card-b">
            <div class="secret"><code id="plain-secret">{{ session('plain_secret') }}</code><button type="button" class="btn btn-sm" data-copy="#plain-secret">Salin</button></div>
            <div class="hint">Verifikasi di sistem Anda: <span class="mono">X-Almara-Signature = "sha256=" + HMAC_SHA256(secret, X-Almara-Timestamp + "." + body)</span>. Tolak bila timestamp lebih dari 5 menit.</div>
        </div>
    </div>
@endif

<div class="card" style="margin-bottom:10px">
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Nama</th><th>URL</th><th class="hide-sm">Event</th><th>Terakhir</th><th class="right">Aksi</th></tr></thead>
        <tbody>
        @forelse ($webhooks as $w)
            <tr>
                <td>{{ $w->name }} @unless ($w->is_active)<span class="tag">nonaktif</span>@endunless</td>
                <td class="mono msg" style="max-width:260px">{{ $w->url }}</td>
                <td class="hide-sm">@foreach ($w->events as $e)<span class="chip">{{ $e }}</span>@endforeach</td>
                <td>@if ($w->last_called_at)<span class="badge {{ $w->last_status >= 200 && $w->last_status < 300 ? 'st-sent' : 'st-failed' }}">{{ $w->last_status ?: 'error' }}</span> <span class="muted">{{ $w->last_called_at->diffForHumans(short: true) }}</span>@else<span class="muted">belum</span>@endif</td>
                <td class="right nowrap">
                    <form method="POST" action="{{ route('webhooks.test', $w) }}" class="inline">@csrf<button class="btn btn-sm" data-once>Tes</button></form>
                    <form method="POST" action="{{ route('webhooks.rotate', $w) }}" class="inline" data-confirm="Buat signing secret baru? Secret lama langsung tidak berlaku.">@csrf<button class="btn btn-sm">Rotasi secret</button></form>
                    <a class="btn btn-sm" href="{{ route('webhooks.edit', $w) }}">Ubah</a>
                    <form method="POST" action="{{ route('webhooks.destroy', $w) }}" class="inline" data-confirm="Hapus webhook {{ $w->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Belum ada webhook. Webhook mengirim event (pesan masuk, status, opt-out, campaign) ke sistem Anda.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-h">Pengiriman terakhir</div>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Waktu</th><th>Webhook</th><th>Event</th><th>HTTP</th><th class="hide-sm">Percobaan</th><th class="hide-sm">Error</th><th></th></tr></thead>
        <tbody>
        @forelse ($deliveries as $d)
            <tr>
                <td class="muted nowrap">{{ $d->created_at->format('d/m H:i:s') }}</td>
                <td>{{ $d->webhook?->name }}</td>
                <td class="mono">{{ $d->event }}</td>
                <td><span class="badge {{ $d->delivered_at ? 'st-sent' : 'st-failed' }}">{{ $d->response_status ?? '—' }}</span></td>
                <td class="hide-sm">{{ $d->attempts }}</td>
                <td class="hide-sm muted msg">{{ $d->error }}</td>
                <td class="right">@unless ($d->delivered_at)<form method="POST" action="{{ route('webhooks.redeliver', $d) }}" class="inline">@csrf<button class="btn btn-sm">Kirim ulang</button></form>@endunless</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Belum ada pengiriman.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
