@extends('layouts.app')
{{-- Pipeline CRM — @2026-Alifiandra --}}
@section('title', 'Pipeline CRM')
@section('crumb', 'Kontak / Pipeline')
@section('actions')
    <a class="btn" href="{{ route('contacts.index') }}">Tampilan daftar</a>
    <a class="btn btn-primary" href="{{ route('contacts.create') }}">+ Kontak</a>
@endsection

@section('content')
<form class="filters card" method="GET" style="margin-bottom:8px">
    <input type="hidden" name="view" value="pipeline">
    <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, nomor…">
    <select class="input" name="tag"><option value="">Semua tag</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(request('tag') == $t->id)>{{ $t->name }}</option>@endforeach</select>
    <button class="btn">Filter</button>
</form>
<div class="kanban">
    @foreach ($columns as $stage => $col)
        <div class="colk">
            <h3><span>{{ $col['label'] }}</span><span>{{ $col['total'] }}</span></h3>
            <div class="items">
                @forelse ($col['items'] as $c)
                    <div class="kcard">
                        <a href="{{ route('contacts.show', $c) }}">{{ $c->displayName() }}</a>
                        <div class="muted mono" style="font-size:11px">{{ $c->maskedPhone() }} · {{ $c->last_message_at?->diffForHumans(short: true) ?? 'belum ada chat' }}</div>
                        <div>@foreach ($c->tags as $t)<span class="chip">{{ $t->name }}</span>@endforeach</div>
                        <form method="POST" action="{{ route('contacts.stage', $c) }}">
                            @csrf @method('PATCH')
                            <select class="input" name="stage" onchange="this.form.submit()" aria-label="Pindah tahap">
                                @foreach (\App\Models\Contact::STAGES as $k => $v)<option value="{{ $k }}" @selected($k === $stage)>{{ $k === $stage ? 'Pindah ke…' : $v }}</option>@endforeach
                            </select>
                        </form>
                    </div>
                @empty
                    <div class="muted" style="font-size:12px;padding:6px">Kosong</div>
                @endforelse
                @if ($col['total'] > $col['items']->count())<a class="muted" style="font-size:11.5px;padding:4px" href="{{ route('contacts.index', ['stage' => $stage]) }}">+{{ $col['total'] - $col['items']->count() }} lainnya</a>@endif
            </div>
        </div>
    @endforeach
</div>
@endsection
