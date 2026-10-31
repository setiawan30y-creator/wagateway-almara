@extends('layouts.app')
{{-- Smart Template — @2026-Alifiandra --}}
@section('title', 'Template Pesan')
@section('crumb', 'Template')
@section('actions')<a class="btn btn-primary" href="{{ route('templates.create') }}">+ Template</a>@endsection

@section('content')
<div class="card">
    <div class="table-wrap">
        <table class="dense">
            <thead><tr><th>Nama</th><th>Kategori</th><th>Varian</th><th class="hide-sm">Performa varian (terkirim · dibaca · gagal)</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($templates as $t)
                <tr>
                    <td><b>{{ $t->name }}</b>@if ($t->media_type) <span class="tag" title="{{ $t->media_name }}">📎 {{ \App\Support\MediaFile::LABELS[$t->media_type] ?? 'file' }}</span>@endif<div class="muted msg" style="max-width:320px;font-size:12px">{{ $t->variants->first()?->body }}</div></td>
                    <td><span class="tag">{{ \App\Models\Template::CATEGORIES[$t->category] ?? $t->category }}</span></td>
                    <td>{{ $t->variants->pluck('label')->implode(' / ') }}</td>
                    <td class="hide-sm mono" style="font-size:11.5px">
                        @foreach ($t->variants as $v)
                            @php $s = $variantStats[$v->id] ?? null; @endphp
                            <div>{{ $v->label }}: {{ $s ? $s->sent.' · '.$s->read_count.' · '.$s->failed : 'belum dipakai' }}</div>
                        @endforeach
                    </td>
                    <td>@if ($t->is_active)<span class="badge st-connected">aktif</span>@else<span class="badge st-unknown">nonaktif</span>@endif</td>
                    <td class="right nowrap">
                        <a class="btn btn-sm" href="{{ route('templates.edit', $t) }}">Ubah</a>
                        <form method="POST" action="{{ route('templates.destroy', $t) }}" class="inline" data-confirm="Hapus template {{ $t->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada template. Buat template untuk broadcast & balasan cepat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
