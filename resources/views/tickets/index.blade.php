@extends('layouts.app')
{{-- Ticketing — @2026-Alifiandra --}}
@section('title', 'Tiket')
@section('crumb', 'Tiket')
@section('actions')<a class="btn btn-primary" href="{{ route('tickets.create') }}">+ Tiket</a>@endsection

@section('content')
<div class="grid grid-4" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Tiket aktif</div><div class="v">{{ $counts['open'] }}</div></div>
    <div class="card kpi"><div class="l">Milik saya</div><div class="v" style="color:var(--info)">{{ $counts['mine'] }}</div></div>
    <div class="card kpi"><div class="l">Lewat SLA</div><div class="v" style="color:var(--err)">{{ $counts['overdue'] }}</div></div>
    <div class="card kpi"><div class="l">SLA normal</div><div class="v" style="font-size:16px">{{ \App\Models\Ticket::slaHours('normal') }} jam</div></div>
</div>
<div class="card">
    <div class="tabs">
        @foreach (['open' => 'Aktif', 'mine' => 'Milik saya', 'overdue' => 'Lewat SLA', 'resolved' => 'Selesai', 'all' => 'Semua'] as $k => $v)
            <a href="{{ route('tickets.index', ['filter' => $k]) }}" class="{{ $filter === $k ? 'active' : '' }}">{{ $v }}</a>
        @endforeach
    </div>
    <form class="filters" method="GET">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor tiket, judul, pelanggan…">
        <select class="input" name="priority"><option value="">Semua prioritas</option>@foreach (\App\Models\Ticket::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>@endforeach</select>
        <select class="input" name="category"><option value="">Semua kategori</option>@foreach (\App\Models\Ticket::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Tiket</th><th>Judul</th><th>Pelanggan</th><th>Prioritas</th><th>Status</th><th class="hide-sm">Agent</th><th>SLA</th></tr></thead>
        <tbody>
        @forelse ($tickets as $t)
            <tr>
                <td class="mono"><a href="{{ route('tickets.show', $t) }}">{{ $t->number }}</a></td>
                <td class="msg">{{ $t->subject }}</td>
                <td>{{ $t->contact?->displayName() }}</td>
                <td><span class="prio-{{ $t->priority }}">{{ \App\Models\Ticket::PRIORITIES[$t->priority] }}</span></td>
                <td><span class="badge st-tk-{{ $t->status }}">{{ $t->statusLabel() }}</span></td>
                <td class="hide-sm muted">{{ $t->assignee?->name ?? '—' }}</td>
                <td class="nowrap">
                    @if (in_array($t->status, ['resolved', 'closed']))<span class="muted">selesai {{ $t->resolved_at?->diffForHumans(short: true) }}</span>
                    @elseif ($t->isOverdue())<span style="color:var(--err);font-weight:600">lewat {{ $t->sla_due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                    @else<span class="muted">{{ $t->sla_due_at?->diffForHumans(short: true) }}</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Tidak ada tiket.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $tickets->links() }}
</div>
@endsection
