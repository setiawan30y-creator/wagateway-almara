@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Dashboard')
@section('crumb', 'Dashboard')
@section('actions')
    <a class="btn" href="{{ route('inbox.index') }}">@include('partials.icon', ['n' => 'inbox']) Inbox</a>
    <a class="btn btn-primary" href="{{ route('send.create') }}">@include('partials.icon', ['n' => 'send']) Kirim Pesan</a>
@endsection

@section('content')
@php $n = fn ($v) => number_format($v, 0, ',', '.'); @endphp
<div class="grid grid-5" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Pesan keluar · 7 hari</div><div class="v">{{ $n($a['messages']['outgoing']) }}</div></div>
    <div class="card kpi"><div class="l">Pesan masuk · 7 hari</div><div class="v" style="color:var(--info)">{{ $n($a['messages']['incoming']) }}</div></div>
    <div class="card kpi"><div class="l">Gagal</div><div class="v" style="color:var(--err)">{{ $n($a['messages']['failed']) }} <small class="muted" style="font-size:12px">{{ $a['messages']['failed_rate'] }}%</small></div></div>
    <div class="card kpi"><div class="l">Percakapan open</div><div class="v" style="color:var(--warn)">{{ $n($a['conversations']['open']) }} <small class="muted" style="font-size:12px">{{ $a['conversations']['unread'] }} belum dibaca</small></div></div>
    <div class="card kpi"><div class="l">Kontak</div><div class="v">{{ $n($a['contacts']['total']) }} <small class="muted" style="font-size:12px">{{ $n($a['contacts']['opted_in']) }} opt-in</small></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:10px">
    <div class="card">
        <div class="card-h">Lalu lintas pesan · 7 hari</div>
        @php $max = max(1, collect($a['daily'])->map(fn ($d) => max($d['in'], $d['out']))->max()); @endphp
        <div class="bars" role="img" aria-label="Grafik pesan masuk dan keluar 7 hari">
            @foreach ($a['daily'] as $d)
                <div class="b" title="{{ $d['date'] }} — keluar {{ $d['out'] }}, masuk {{ $d['in'] }}">
                    <div style="display:flex;gap:2px;align-items:flex-end;height:100%;width:100%;justify-content:center">
                        <i class="o" style="height:{{ max(2, $d['out'] * 100 / $max) }}%"></i>
                        <i class="n" style="height:{{ max(2, $d['in'] * 100 / $max) }}%"></i>
                    </div>
                    <small>{{ \Illuminate\Support\Carbon::parse($d['date'])->format('d/m') }}</small>
                </div>
            @endforeach
        </div>
        <div class="legend"><span><i style="background:var(--primary)"></i>Keluar</span><span><i style="background:var(--info)"></i>Masuk</span></div>
    </div>

    <div class="card">
        <div class="card-h">Percakapan terbaru <a class="r" href="{{ route('inbox.index') }}">Inbox</a></div>
        <div class="table-wrap">
            <table class="dense">
                <tbody>
                @forelse ($conversations as $c)
                    <tr>
                        <td><a href="{{ route('inbox.show', $c) }}">{{ $c->contact->displayName() }}</a> @if ($c->unread_count)<span class="count" style="display:inline-flex">{{ $c->unread_count }}</span>@endif</td>
                        <td class="msg muted hide-sm">{{ $c->last_direction === 'out' ? 'Anda: ' : '' }}{{ $c->last_message_preview }}</td>
                        <td class="right muted nowrap">{{ $c->last_message_at?->diffForHumans(short: true) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Belum ada percakapan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Perangkat @can('admin')<a class="r" href="{{ route('devices.index') }}">Kelola</a>@endcan</div>
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
                    <tr><td colspan="4" class="empty">Belum ada perangkat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Broadcast aktif @can('admin')<a class="r" href="{{ route('campaigns.index') }}">Semua</a>@endcan</div>
        <div class="table-wrap">
            <table class="dense">
                <tbody>
                @forelse ($campaigns as $c)
                    @php $st = $c->stats(); @endphp
                    <tr>
                        <td>@can('admin')<a href="{{ route('campaigns.show', $c) }}">{{ $c->name }}</a>@else{{ $c->name }}@endcan</td>
                        <td><span class="tag">{{ $c->statusLabel() }}</span></td>
                        <td style="width:35%"><div class="progress"><i style="width:{{ $st['progress'] }}%"></i></div></td>
                        <td class="right mono">{{ $st['sent'] }}/{{ $st['total'] }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Tidak ada broadcast berjalan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
