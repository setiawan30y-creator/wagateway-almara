@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Detail Pesan')
@section('crumb', 'Riwayat / '.$message->public_id)
@section('actions')
    @if ($message->status === 'failed')
        <form method="POST" action="{{ route('messages.retry', $message) }}" class="inline" data-confirm="Kirim ulang pesan ini?">
            @csrf
            <button class="btn btn-primary" data-once>Kirim ulang</button>
        </form>
    @endif
    <a class="btn" href="{{ route('messages.index') }}">Kembali</a>
@endsection

@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Informasi</div>
        <div class="card-b">
            <dl class="kv">
                <dt>ID</dt><dd class="mono">{{ $message->public_id }}</dd>
                <dt>Status</dt><dd><span class="badge st-{{ $message->status }}">{{ $message->status }}</span></dd>
                <dt>Tujuan</dt><dd class="mono">+{{ $message->to }}</dd>
                <dt>Perangkat</dt><dd>{{ $message->device?->name ?? '—' }} <span class="muted">{{ $message->device?->providerLabel() }}</span></dd>
                <dt>Sumber</dt><dd>{{ $message->apiKey ? 'API ('.$message->apiKey->name.')' : 'Panel' }}{{ $message->user ? ' — '.$message->user->name : '' }}</dd>
                <dt>ID provider</dt><dd class="mono">{{ $message->provider_message_id ?? '—' }}</dd>
                <dt>Percobaan</dt><dd>{{ $message->attempts }}</dd>
                <dt>Dibuat</dt><dd>{{ $message->created_at->format('d/m/Y H:i:s') }}</dd>
                <dt>Terkirim</dt><dd>{{ $message->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                @if ($message->error)<dt>Error</dt><dd style="color:var(--err)">{{ $message->error }}</dd>@endif
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Isi pesan</div>
        <div class="card-b"><pre class="code" style="white-space:pre-wrap;font-family:inherit;font-size:13px">{{ $message->body }}</pre></div>
    </div>
</div>
@endsection
