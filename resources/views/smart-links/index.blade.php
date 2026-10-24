@extends('layouts.app')
{{-- Smart Link — @2026-Alifiandra --}}
@section('title', 'Smart Link')
@section('crumb', 'Smart Link')
@section('actions')<a class="btn btn-primary" href="{{ route('smart-links.create') }}">+ Smart Link</a>@endsection

@section('content')
<div class="card">
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Nama</th><th>Link</th><th class="right">Klik</th><th class="right">Chat</th><th class="right">Konversi</th><th class="hide-sm">Tag</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($links as $l)
            <tr>
                <td><a href="{{ route('smart-links.show', $l) }}">{{ $l->name }}</a></td>
                <td class="mono">/l/{{ $l->slug }}</td>
                <td class="right mono">{{ number_format($l->clicks_count, 0, ',', '.') }}</td>
                <td class="right mono">{{ number_format($l->chats_count, 0, ',', '.') }}</td>
                <td class="right mono">{{ $l->conversions_count }}</td>
                <td class="hide-sm">@if ($l->tag)<span class="chip">{{ $l->tag->name }}</span>@else — @endif</td>
                <td>@if ($l->isAvailable())<span class="badge st-connected">aktif</span>@elseif ($l->expires_at?->isPast())<span class="badge st-unknown">kedaluwarsa</span>@elseif (! $l->targetPhone())<span class="badge st-failed">nomor kosong</span>@else<span class="badge st-unknown">nonaktif</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Belum ada smart link. Buat link klik-ke-chat untuk website, Instagram, brosur, atau iklan.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
