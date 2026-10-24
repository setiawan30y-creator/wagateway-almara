@extends('layouts.app')
{{-- Broadcast Center — @2026-Alifiandra --}}
@section('title', 'Broadcast')
@section('crumb', 'Broadcast')
@section('actions')<a class="btn btn-primary" href="{{ route('campaigns.create') }}">+ Campaign</a>@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="dense">
            <thead><tr><th>Campaign</th><th>Status</th><th class="hide-sm">Template</th><th class="hide-sm">Perangkat</th><th style="width:18%">Progres</th><th class="right">Terkirim</th><th class="right hide-sm">Gagal</th><th class="hide-sm">Jadwal / mulai</th></tr></thead>
            <tbody>
            @forelse ($campaigns as $c)
                @php $st = $c->stats(); @endphp
                <tr>
                    <td><a href="{{ route('campaigns.show', $c) }}">{{ $c->name }}</a></td>
                    <td><span class="tag">{{ $c->statusLabel() }}</span></td>
                    <td class="hide-sm">{{ $c->template?->name ?? '—' }}</td>
                    <td class="hide-sm">{{ $c->device?->name ?? '—' }}</td>
                    <td><div class="progress"><i style="width:{{ $st['progress'] }}%"></i></div></td>
                    <td class="right mono">{{ $st['sent'] }}/{{ $st['total'] }}</td>
                    <td class="right mono hide-sm">{{ $st['failed'] }}</td>
                    <td class="hide-sm muted">{{ ($c->started_at ?? $c->scheduled_at)?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Belum ada campaign.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $campaigns->links() }}
</div>
@endsection
