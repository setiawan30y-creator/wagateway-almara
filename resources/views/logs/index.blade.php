@extends('layouts.app')
{{-- Log — @2026-Alifiandra --}}
@section('title', 'Log')
@section('crumb', 'Log')

@section('content')
<div class="card">
    <div class="tabs">
        @foreach (['audit' => 'Audit', 'api' => 'API', 'webhook' => 'Webhook'] as $k => $v)
            <a href="{{ route('logs.index', ['tab' => $k]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $v }}</a>
        @endforeach
    </div>
    <div class="table-wrap"><table class="dense">
        @if ($tab === 'audit')
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th class="hide-sm">IP</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr><td class="muted nowrap">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $r->user?->name ?? 'Sistem' }}</td><td class="mono">{{ $r->action }}</td><td>{{ $r->description }}</td><td class="hide-sm mono muted">{{ $r->ip }}</td></tr>
            @empty <tr><td colspan="5" class="empty">Kosong.</td></tr> @endforelse
            </tbody>
        @elseif ($tab === 'api')
            <thead><tr><th>Waktu</th><th>API key</th><th>Request</th><th>Status</th><th class="right">Durasi</th><th class="hide-sm">Request ID</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr><td class="muted nowrap">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $r->apiKey?->name ?? '—' }}</td><td class="mono">{{ $r->method }} {{ $r->path }}</td>
                    <td><span class="badge {{ $r->status < 400 ? 'st-sent' : 'st-failed' }}">{{ $r->status }}</span></td><td class="right mono">{{ $r->duration_ms }} ms</td><td class="hide-sm mono muted">{{ $r->request_id }}</td></tr>
            @empty <tr><td colspan="6" class="empty">Kosong.</td></tr> @endforelse
            </tbody>
        @else
            <thead><tr><th>Waktu</th><th>Webhook</th><th>Event</th><th>HTTP</th><th>Percobaan</th><th class="hide-sm">Error</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr><td class="muted nowrap">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $r->webhook?->name }}</td><td class="mono">{{ $r->event }}</td>
                    <td><span class="badge {{ $r->delivered_at ? 'st-sent' : 'st-failed' }}">{{ $r->response_status ?? '—' }}</span></td><td>{{ $r->attempts }}</td><td class="hide-sm muted">{{ $r->error }}</td></tr>
            @empty <tr><td colspan="6" class="empty">Kosong.</td></tr> @endforelse
            </tbody>
        @endif
    </table></div>
    {{ $rows->links() }}
</div>
@endsection
