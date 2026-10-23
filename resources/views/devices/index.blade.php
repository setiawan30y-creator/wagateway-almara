@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Perangkat')
@section('crumb', 'Perangkat')
@section('actions')
    <a class="btn btn-primary" href="{{ route('devices.create') }}">+ Tambah Perangkat</a>
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
                    <td><span class="badge st-{{ $d->status }}">{{ $d->status }}</span></td>
                    <td class="hide-sm muted">{{ $d->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    <td class="hide-sm right">{{ number_format($d->messages_count, 0, ',', '.') }}</td>
                    <td class="right nowrap">
                        <form method="POST" action="{{ route('devices.check', $d) }}" class="inline">@csrf<button class="btn btn-sm" data-once>Cek</button></form>
                        @if ($d->provider === 'qr')<a class="btn btn-sm" href="{{ route('devices.pair', $d) }}">Pairing</a>@endif
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
            @empty
                <tr><td colspan="7" class="empty">Belum ada perangkat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
