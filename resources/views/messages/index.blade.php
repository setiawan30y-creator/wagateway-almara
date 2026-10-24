@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Riwayat Pesan')
@section('crumb', 'Riwayat')
@section('actions')
    <a class="btn" href="{{ route('messages.export') }}">@include('partials.icon', ['n' => 'download']) Export CSV</a>
    <a class="btn btn-primary" href="{{ route('send.create') }}">@include('partials.icon', ['n' => 'send']) Kirim Pesan</a>
@endsection

@section('content')
<div class="card">
    <form class="filters" method="GET" action="{{ route('messages.index') }}">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor, isi pesan, atau ID…">
        <select class="input" name="status">
            <option value="">Semua status</option>
            @foreach ($statuses as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach
        </select>
        <select class="input" name="device">
            <option value="">Semua perangkat</option>
            @foreach ($devices as $d)<option value="{{ $d->id }}" @selected(request('device') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <button class="btn">Filter</button>
        @if (request()->hasAny(['q', 'status', 'device']))<a class="btn" href="{{ route('messages.index') }}">Reset</a>@endif
    </form>
    <div class="table-wrap">
        <table class="dense">
            <thead><tr><th>Waktu</th><th>Nomor</th><th class="hide-sm">Perangkat</th><th>Status</th><th class="hide-sm">Pesan</th><th class="hide-sm">Sumber</th><th></th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr>
                    <td class="nowrap muted">{{ $m->created_at->format('d/m H:i') }}</td>
                    <td class="mono">{{ $m->maskedTo() }}</td>
                    <td class="hide-sm">{{ $m->device?->name ?? '—' }}</td>
                    <td><span class="badge st-{{ $m->status }}" @if ($m->error) title="{{ $m->error }}" @endif>{{ $m->status }}</span></td>
                    <td class="msg hide-sm">{{ $m->preview() }}</td>
                    <td class="hide-sm"><span class="tag">{{ $m->direction === 'in' ? 'Masuk' : ($m->campaign_id ? 'Broadcast' : ($m->api_key_id ? 'API' : 'Panel')) }}</span></td>
                    <td class="right"><a class="btn btn-sm" href="{{ route('messages.show', $m) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada pesan yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $messages->links() }}
</div>
@endsection
