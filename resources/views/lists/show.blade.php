@extends('layouts.app')
{{-- Detail rombongan & anggota — @2026-Alifiandra --}}
@section('title', $list->name)
@section('crumb', 'Rombongan / Detail')
@section('actions')
    @can('admin')<a class="btn btn-primary" href="{{ route('campaigns.create', ['rombongan' => $list->id]) }}">@include('partials.icon', ['n' => 'broadcast']) Broadcast ke rombongan ini</a>@endcan
    <a class="btn" href="{{ route('lists.edit', $list) }}">Ubah</a>
    <form method="POST" action="{{ route('lists.destroy', $list) }}" class="inline" data-confirm="Hapus rombongan {{ $list->name }}? Kontak tetap tersimpan.">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form>
@endsection

@section('content')
@if ($list->description)<div class="hint" style="margin-bottom:8px">{{ $list->description }}</div>@endif
<div class="grid grid-3" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Anggota</div><div class="v">{{ number_format($counts['total'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Siap broadcast (opt-in)</div><div class="v" style="color:var(--ok)">{{ number_format($counts['opted_in'], 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Belum izin · opt-out</div><div class="v">{{ $counts['total'] - $counts['opted_in'] - $counts['opted_out'] }} <small class="muted" style="font-size:12px">· {{ $counts['opted_out'] }}</small></div></div>
</div>

<div class="card" style="margin-bottom:10px">
    <div class="card-h">Tambah anggota</div>
    <div class="card-b">
        <div class="grid grid-2">
            <form method="POST" action="{{ route('lists.add', $list) }}">
                @csrf <input type="hidden" name="source" value="numbers">
                <div class="field"><label for="numbers">Tempel nomor WhatsApp (boleh dengan nama)</label>
                    <textarea class="input" id="numbers" name="numbers" rows="5" placeholder="Satu orang per baris: nomor, nama&#10;081234567890, Nama Lengkap&#10;Nama Pelanggan - 0813 0000 0000&#10;081500000000"></textarea>
                    <div class="hint">Nama dipakai untuk <span class="mono">{{ '{'.'{name}'.'}' }}</span> / <span class="mono">{{ '{'.'{first_name}'.'}' }}</span> di template. Kontak yang sudah punya nama tidak ditimpa. Bisa juga salin 2 kolom (Nomor | Nama) langsung dari Excel.</div>
                    @error('numbers')<div class="error-text">{{ $message }}</div>@enderror</div>
                <label class="check"><input type="checkbox" name="consent" value="1"> Nomor-nomor ini sudah memberi izin menerima pesan (opt-in)</label>
                <div style="margin-top:6px"><button class="btn btn-primary btn-sm" data-once>Tambahkan</button></div>
            </form>
            <div>
                <form method="POST" action="{{ route('lists.add', $list) }}" class="field">
                    @csrf <input type="hidden" name="source" value="tag">
                    <label>Dari tag</label>
                    <div style="display:flex;gap:4px"><select class="input" name="tag_id" required><option value="">Pilih tag…</option>@foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->contacts_count }})</option>@endforeach</select><button class="btn btn-sm" data-once>Tambah</button></div>
                </form>
                <form method="POST" action="{{ route('lists.add', $list) }}" class="field">
                    @csrf <input type="hidden" name="source" value="group">
                    <label>Dari anggota grup WhatsApp</label>
                    <div style="display:flex;gap:4px"><select class="input" name="group_id" required><option value="">Pilih grup…</option>@foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->subject }} ({{ $g->participants_count }})</option>@endforeach</select><button class="btn btn-sm" data-once>Tambah</button></div>
                    @if ($groups->isEmpty())<div class="hint">Belum ada grup — sinkron di menu <a href="{{ route('groups.index') }}">Grup WA</a>.</div>@endif
                </form>
                <form method="POST" action="{{ route('lists.add', $list) }}" class="field">
                    @csrf <input type="hidden" name="source" value="list">
                    <label>Salin dari rombongan lain</label>
                    <div style="display:flex;gap:4px"><select class="input" name="list_id" required><option value="">Pilih rombongan…</option>@foreach ($otherLists as $o)<option value="{{ $o->id }}">{{ $o->name }} ({{ $o->contacts_count }})</option>@endforeach</select><button class="btn btn-sm" data-once>Tambah</button></div>
                </form>
                <div class="hint">Bisa juga dari menu <a href="{{ route('contacts.index') }}">Kontak</a>: centang kontak → Aksi massal → <b>Masukkan ke rombongan</b>.</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <form class="filters" method="GET">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari anggota…">
        <button class="btn">Cari</button>
    </form>
    <form method="POST" action="{{ route('lists.remove', $list) }}" data-confirm="Keluarkan kontak terpilih dari rombongan?">
        @csrf
        <div class="bulkbar"><span class="muted">Terpilih:</span> <button class="btn btn-sm">Keluarkan dari rombongan</button></div>
        <div class="table-wrap"><table class="dense">
            <thead><tr><th style="width:28px"><input type="checkbox" data-check-all="input[name='ids[]']"></th><th>Nama</th><th>Nomor</th><th>Izin</th><th class="hide-sm">Tag</th></tr></thead>
            <tbody>
            @forelse ($members as $c)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
                    <td><a href="{{ route('contacts.show', $c) }}">{{ $c->displayName() }}</a></td>
                    <td class="mono">+{{ $c->phone }}</td>
                    <td><span class="consent-{{ $c->consent }}">{{ \App\Models\Contact::CONSENTS[$c->consent] }}</span></td>
                    <td class="hide-sm">@foreach ($c->tags as $t)<span class="chip">{{ $t->name }}</span>@endforeach</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada anggota. Tambahkan lewat kotak di atas.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </form>
    @if ($members->hasPages()){{ $members->links() }}@endif
</div>
@endsection
