@extends('layouts.app')
{{-- Rombongan penerima — @2026-Alifiandra --}}
@section('title', 'Rombongan Penerima')
@section('crumb', 'Rombongan')
@section('actions')<a class="btn btn-primary" href="{{ route('lists.create') }}">+ Rombongan</a>@endsection

@section('content')
<div class="card">
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Rombongan</th><th class="right">Anggota</th><th class="right">Siap broadcast (opt-in)</th><th class="hide-sm">Keterangan</th><th class="right">Aksi</th></tr></thead>
        <tbody>
        @forelse ($lists as $l)
            <tr>
                <td><a href="{{ route('lists.show', $l) }}"><b>{{ $l->name }}</b></a></td>
                <td class="right mono">{{ number_format($l->contacts_count, 0, ',', '.') }}</td>
                <td class="right mono">{{ number_format($l->opted_in_count, 0, ',', '.') }}</td>
                <td class="hide-sm muted">{{ $l->description ?: '—' }}</td>
                <td class="right nowrap">
                    <a class="btn btn-sm" href="{{ route('lists.show', $l) }}">Anggota</a>
                    @can('admin')<a class="btn btn-sm btn-primary" href="{{ route('campaigns.create', ['rombongan' => $l->id]) }}">Broadcast</a>@endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Belum ada rombongan.<br><span class="hint">Buat rombongan per peruntukan (mis. “Reseller”, “Pelanggan VIP”, “Peserta Event”), lalu pilih saat membuat broadcast.</span></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
