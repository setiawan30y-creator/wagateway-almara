@extends('layouts.app')
{{-- Grup WhatsApp — @2026-Alifiandra --}}
@section('title', 'Grup WhatsApp')
@section('crumb', 'Grup WA')
@section('actions')
    <form method="POST" action="{{ route('groups.sync', $deviceId ? ['device' => $deviceId] : []) }}" class="inline">@csrf<button class="btn btn-primary" data-once @disabled($devices->isEmpty())>Sinkron grup dari HP</button></form>
@endsection

@section('content')
@if ($devices->isEmpty())
    <div class="alert alert-warning">Grup hanya tersedia untuk perangkat <b>Scan QR</b>. <a href="{{ route('devices.index') }}">Hubungkan nomor</a> terlebih dahulu.</div>
@endif
<div class="card">
    <form class="filters" method="GET">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama grup…">
        @if ($devices->count() > 1)
            <select class="input" name="device"><option value="">Semua perangkat</option>@foreach ($devices as $d)<option value="{{ $d->id }}" @selected($deviceId === $d->id)>{{ $d->name }}</option>@endforeach</select>
        @endif
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Grup</th><th class="right">Anggota</th><th class="hide-sm">Perangkat</th><th class="hide-sm">Pesan terakhir</th><th class="hide-sm">Disinkron</th><th class="right">Aksi</th></tr></thead>
        <tbody>
        @forelse ($groups as $g)
            <tr>
                <td>
                    <a href="{{ route('groups.show', $g) }}"><b>{{ $g->subject }}</b></a>
                    @if ($g->is_admin)<span class="tag">admin</span>@endif
                    @if ($g->announce)<span class="tag" title="Hanya admin yang boleh mengirim">khusus admin</span>@endif
                </td>
                <td class="right mono">{{ $g->participants_count ?: '—' }}</td>
                <td class="hide-sm">{{ $g->device?->name }}</td>
                <td class="hide-sm muted">{{ $g->conversation?->last_message_at?->diffForHumans() ?? '—' }}</td>
                <td class="hide-sm muted">{{ $g->synced_at?->diffForHumans() ?? '—' }}</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-primary" href="{{ route('groups.chat', $g) }}">Buka chat</a>
                    <a class="btn btn-sm" href="{{ route('groups.show', $g) }}">Anggota</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Belum ada grup. Klik <b>Sinkron grup dari HP</b> setelah perangkat Scan QR terhubung.<br><span class="hint">Grup juga muncul otomatis saat ada pesan masuk dari grup.</span></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($groups->hasPages()){{ $groups->links() }}@endif
</div>
@endsection
