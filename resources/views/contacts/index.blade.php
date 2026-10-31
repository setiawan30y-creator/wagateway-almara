@extends('layouts.app')
{{-- Kontak & CRM — @2026-Alifiandra --}}
@section('title', 'Kontak & CRM')
@section('crumb', 'Kontak')
@section('actions')
    <a class="btn" href="{{ route('contacts.index', ['view' => 'pipeline']) }}">Pipeline</a>
    <form method="POST" action="{{ route('contacts.sync') }}" class="inline" title="Ambil kontak yang tersimpan di HP dari perangkat Scan QR yang terhubung">@csrf<button class="btn" data-once>@include('partials.icon', ['n' => 'device']) Sinkron dari HP</button></form>
    <details style="position:relative">
        <summary class="btn">Import CSV</summary>
        <div class="card" style="position:absolute;right:0;top:32px;z-index:15;width:320px">
            <form class="card-b" method="POST" action="{{ route('contacts.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="field"><label>File CSV</label><input class="input" type="file" name="file" accept=".csv,text/csv" required style="height:auto;padding:4px"></div>
                <div class="hint" style="margin-bottom:8px">Header kolom: <span class="mono">name, phone, email, tags</span> (pemisah koma atau titik koma; tags dipisah koma dalam tanda kutip).</div>
                <label class="check"><input type="checkbox" name="consent" value="1"> Kontak ini sudah memberi izin (opt-in)</label>
                <div style="margin-top:8px"><button class="btn btn-primary btn-sm" data-once>Import</button></div>
            </form>
        </div>
    </details>
    <a class="btn" href="{{ route('contacts.export') }}">@include('partials.icon', ['n' => 'download']) Export</a>
    <a class="btn btn-primary" href="{{ route('contacts.create') }}">+ Kontak</a>
@endsection

@section('content')
<div class="card">
    <form class="filters" method="GET">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, nomor, email…">
        <select class="input" name="stage"><option value="">Semua tahap</option>@foreach (\App\Models\Contact::STAGES as $k => $v)<option value="{{ $k }}" @selected(request('stage') === $k)>{{ $v }}</option>@endforeach</select>
        <select class="input" name="tag"><option value="">Semua tag</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(request('tag') == $t->id)>{{ $t->name }}</option>@endforeach</select>
        <select class="input" name="consent"><option value="">Semua izin</option>@foreach (\App\Models\Contact::CONSENTS as $k => $v)<option value="{{ $k }}" @selected(request('consent') === $k)>{{ $v }}</option>@endforeach</select>
        <button class="btn">Filter</button>
        @if (request()->hasAny(['q', 'stage', 'tag', 'consent']))<a class="btn" href="{{ route('contacts.index') }}">Reset</a>@endif
    </form>

    <form method="POST" action="{{ route('contacts.bulk') }}" id="bulk" data-confirm="Terapkan aksi ke kontak terpilih?">
        @csrf
        <div class="bulkbar">
            <span class="muted">Aksi massal:</span>
            <select class="input" name="action" required>
                <option value="tag">Tambah tag</option>
                <option value="untag">Hapus tag</option>
                <option value="list">Masukkan ke rombongan (isi nama)</option>
                <option value="opt_in">Tandai opt-in</option>
                <option value="opt_out">Tandai opt-out</option>
                <option value="stage">Ubah tahap (isi kode tahap)</option>
                @can('admin')<option value="delete">Hapus kontak</option>@endcan
            </select>
            <input class="input" name="value" placeholder="Nilai, mis. vip / nama rombongan" style="width:190px">
            <button class="btn btn-sm">Terapkan</button>
        </div>
        <div class="table-wrap">
            <table class="dense">
                <thead><tr>
                    <th style="width:28px"><input type="checkbox" data-check-all=".row-check" aria-label="Pilih semua"></th>
                    <th>Nama</th><th>WhatsApp</th><th class="hide-sm">Tahap</th><th class="hide-sm">Tag</th><th>Izin</th><th class="hide-sm">Agent</th><th class="hide-sm">Kontak terakhir</th>
                </tr></thead>
                <tbody>
                @forelse ($contacts as $c)
                    <tr>
                        <td><input type="checkbox" class="row-check" name="ids[]" value="{{ $c->id }}" aria-label="Pilih"></td>
                        <td><a href="{{ route('contacts.show', $c) }}">{{ $c->name ?: '—' }}</a></td>
                        <td class="mono">{{ $c->maskedPhone() }}</td>
                        <td class="hide-sm"><span class="tag">{{ $c->stageLabel() }}</span></td>
                        <td class="hide-sm">@foreach ($c->tags as $t)<span class="chip">{{ $t->name }}</span>@endforeach</td>
                        <td class="consent-{{ $c->consent }}" style="font-size:12px">{{ \App\Models\Contact::CONSENTS[$c->consent] }}</td>
                        <td class="hide-sm muted">{{ $c->assignee?->name ?? '—' }}</td>
                        <td class="hide-sm muted">{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">Belum ada kontak. Kontak dibuat otomatis saat ada pesan masuk/keluar, atau tambahkan/import manual.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>
    {{ $contacts->links() }}
</div>
@endsection
