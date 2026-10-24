@extends('layouts.app')
{{-- Detail tiket — @2026-Alifiandra --}}
@section('title', $ticket->number.' — '.$ticket->subject)
@section('crumb', 'Tiket / Detail')
@section('actions')
    @if ($ticket->contact)
        <form method="POST" action="{{ route('inbox.start') }}" class="inline">@csrf<input type="hidden" name="contact_id" value="{{ $ticket->contact_id }}"><button class="btn">@include('partials.icon', ['n' => 'inbox']) Buka chat</button></form>
    @endif
    @if (in_array($ticket->status, ['open', 'pending']))
        <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="inline">@csrf @method('PATCH')<input type="hidden" name="status" value="resolved"><input type="hidden" name="notify_customer" value="1"><button class="btn btn-primary" data-once>Selesaikan & beri tahu pelanggan</button></form>
    @endif
@endsection

@section('content')
@if ($ticket->isOverdue())<div class="alert alert-error">Tiket melewati SLA ({{ $ticket->sla_due_at->format('d/m/Y H:i') }}).@if ($ticket->escalated_at) Sudah dieskalasi {{ $ticket->escalated_at->diffForHumans() }}.@endif</div>@endif
<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-h">Detail</div>
            <div class="card-b">
                <form method="POST" action="{{ route('tickets.update', $ticket) }}">
                    @csrf @method('PATCH')
                    <div class="grid grid-2" style="gap:0 10px">
                        <div class="field"><label>Status</label><select class="input" name="status">@foreach (\App\Models\Ticket::STATUSES as $k => $v)<option value="{{ $k }}" @selected($ticket->status === $k)>{{ $v }}</option>@endforeach</select></div>
                        <div class="field"><label>Prioritas</label><select class="input" name="priority">@foreach (\App\Models\Ticket::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($ticket->priority === $k)>{{ $v }}</option>@endforeach</select></div>
                        <div class="field"><label>Kategori</label><select class="input" name="category">@foreach (\App\Models\Ticket::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($ticket->category === $k)>{{ $v }}</option>@endforeach</select></div>
                        <div class="field"><label>Ditugaskan ke</label><select class="input" name="assigned_to"><option value="">—</option>@foreach ($agents as $a)<option value="{{ $a->id }}" @selected($ticket->assigned_to === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                    </div>
                    <label class="check"><input type="checkbox" name="notify_customer" value="1"> Beri tahu pelanggan bila diselesaikan</label>
                    <div style="margin-top:6px"><button class="btn btn-primary btn-sm" data-once>Simpan perubahan</button></div>
                </form>
                <dl class="kv" style="margin-top:10px">
                    <dt>Pelanggan</dt><dd>@if ($ticket->contact)<a href="{{ route('contacts.show', $ticket->contact) }}">{{ $ticket->contact->displayName() }}</a> <span class="muted mono">+{{ $ticket->contact->phone }}</span>@else — @endif</dd>
                    <dt>Batas SLA</dt><dd>{{ $ticket->sla_due_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt>Dibuat</dt><dd>{{ $ticket->created_at->format('d/m/Y H:i') }} · {{ $ticket->creator?->name ?? 'API' }}</dd>
                    <dt>Selesai</dt><dd>{{ $ticket->resolved_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>
                @if ($ticket->description)<div class="note" style="margin-top:8px">{{ $ticket->description }}</div>@endif
            </div>
        </div>
        <div class="card">
            <div class="card-h">Chat terakhir pelanggan</div>
            <div class="thread" style="max-height:320px;flex:none">
                @forelse ($recent as $m)
                    <div class="bubble {{ $m->direction === 'in' ? 'in' : 'out' }}">@include('partials.media', ['m' => $m])<span class="txt">{{ $m->hasMedia() && preg_match('/^\[[a-z]+\]$/', $m->body) ? '' : $m->body }}</span><span class="meta">{{ $m->created_at->format('d/m H:i') }}</span></div>
                @empty
                    <span class="day-sep">Belum ada chat.</span>
                @endforelse
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Timeline</div>
        <div class="card-b">
            <form method="POST" action="{{ route('tickets.note', $ticket) }}" style="margin-bottom:10px">
                @csrf
                <textarea class="input" name="body" rows="2" placeholder="Tambah catatan internal…" required></textarea>
                <button class="btn btn-sm" style="margin-top:4px">Tambah catatan</button>
            </form>
            @foreach ($ticket->events as $e)
                <div class="note" style="{{ $e->type === 'note' ? '' : 'background:var(--panel-2);border-color:var(--border)' }}">@if ($e->type === 'escalated')<b style="color:var(--err)">Eskalasi</b> — @endif<span class="txt">{{ $e->body }}</span>
                    <small>{{ $e->user?->name ?? 'Sistem' }} · {{ $e->created_at->format('d/m/Y H:i') }}</small>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
