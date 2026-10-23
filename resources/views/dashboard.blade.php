@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')
@section('actions')
    <a class="btn btn-primary" href="{{ route('send.create') }}">@include('partials.icon', ['n' => 'send']) Kirim Pesan</a>
@endsection

@section('content')
<div class="grid grid-5" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Pesan hari ini</div><div class="v">{{ number_format($stats['today'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Terkirim hari ini</div><div class="v" style="color:var(--ok)">{{ number_format($stats['sent_today'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Gagal hari ini</div><div class="v" style="color:var(--err)">{{ number_format($stats['failed_today'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Dalam antrean</div><div class="v" style="color:var(--warn)">{{ number_format($stats['queued'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Total pesan</div><div class="v">{{ number_format($stats['total'], 0, ',', '.') }}</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Perangkat <a class="r" href="{{ route('devices.index') }}">Kelola</a></div>
        <div class="table-wrap">
            <table class="dense">
                <thead><tr><th>Nama</th><th>Provider</th><th>Status</th><th class="hide-sm">Terakhir aktif</th></tr></thead>
                <tbody>
                @forelse ($devices as $d)
                    <tr>
                        <td>{{ $d->name }} @if ($d->is_default)<span class="tag">utama</span>@endif</td>
                        <td>{{ $d->providerLabel() }}</td>
                        <td><span class="badge st-{{ $d->status }}">{{ $d->status }}</span></td>
                        <td class="hide-sm muted">{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada perangkat. <a href="{{ route('devices.create') }}">Tambah perangkat</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Pesan terbaru <a class="r" href="{{ route('messages.index') }}">Semua</a></div>
        <div class="table-wrap">
            <table class="dense">
                <thead><tr><th>Waktu</th><th>Tujuan</th><th>Status</th><th class="hide-sm">Pesan</th></tr></thead>
                <tbody>
                @forelse ($recent as $m)
                    <tr>
                        <td class="nowrap muted">{{ $m->created_at->format('H:i') }}</td>
                        <td class="mono"><a href="{{ route('messages.show', $m) }}">{{ $m->maskedTo() }}</a></td>
                        <td><span class="badge st-{{ $m->status }}">{{ $m->status }}</span></td>
                        <td class="msg hide-sm">{{ $m->body }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada pesan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
