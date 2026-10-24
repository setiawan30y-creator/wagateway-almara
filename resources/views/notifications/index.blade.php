@extends('layouts.app')
{{-- Notification Center — @2026-Alifiandra --}}
@section('title', 'Notifikasi')
@section('crumb', 'Notifikasi')
@section('actions')
    <form method="POST" action="{{ route('notifications.readAll') }}" class="inline">@csrf<button class="btn">Tandai semua dibaca</button></form>
@endsection

@section('content')
<div class="card">
    <div class="table-wrap"><table class="dense">
        <tbody>
        @forelse ($notifications as $n)
            <tr style="{{ $n->read_at ? 'opacity:.65' : '' }}">
                <td style="width:12px">@unless ($n->read_at)<span class="badge st-queued" style="border:0;padding:0" title="Belum dibaca"></span>@endunless</td>
                <td><a href="{{ route('notifications.open', $n) }}"><b>{{ $n->title }}</b></a><div class="muted msg" style="max-width:600px">{{ $n->body }}</div></td>
                <td class="hide-sm"><span class="tag">{{ $n->type }}</span></td>
                <td class="right muted nowrap">{{ $n->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td class="empty">Belum ada notifikasi.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $notifications->links() }}
</div>
@endsection
