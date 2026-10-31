@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $template->exists ? 'Ubah Template' : 'Template Baru')
@section('crumb', 'Template')

@section('content')
<form method="POST" action="{{ $template->exists ? route('templates.update', $template) : route('templates.store') }}" enctype="multipart/form-data">
@csrf
@if ($template->exists) @method('PUT') @endif
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Template</div>
        <div class="card-b">
            <div class="field"><label for="name">Nama template</label><input class="input" id="name" name="name" value="{{ old('name', $template->name) }}" placeholder="Contoh: Info promo bulanan" required maxlength="100"></div>
            <div class="field"><label for="category">Kategori</label>
                <select class="input" id="category" name="category">@foreach (\App\Models\Template::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $template->category) === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))> Aktif</label></div>
            <div class="hint" style="margin-bottom:6px">Variabel tersedia (klik untuk sisipkan ke varian A):</div>
            @php $wrap = fn ($v) => str_repeat('{', 2).$v.str_repeat('}', 2); @endphp
            <div>@foreach (\App\Models\Template::VARIABLES as $var)<button type="button" class="btn btn-sm" data-insert="{{ $wrap($var) }}" data-target="#v-A" style="margin:0 4px 4px 0">{{ $wrap($var) }}</button>@endforeach</div>
            <div class="hint" style="margin-top:8px">Varian B & C opsional. Saat broadcast, tiap penerima mendapat salah satu varian secara acak; performa tiap varian terlihat di daftar template & detail campaign.</div>
            <div class="hint" style="margin-top:6px"><b>Anti-seragam:</b> tulis pilihan kata dengan format <span class="mono">{Halo|Hai|Selamat siang}</span> — setiap penerima mendapat salah satunya secara acak. Contoh: <span class="mono">{Halo|Hai} {{ $wrap('first_name') }}, {ada promo|ada penawaran spesial} untuk Anda.</span></div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Pratinjau varian A</div>
        <div class="card-b"><pre class="code" id="pv-A" style="white-space:pre-wrap;font-family:inherit;font-size:13px;min-height:80px"></pre></div>
    </div>
</div>
<div class="grid grid-3" style="margin-top:10px">
    @foreach (['A', 'B', 'C'] as $label)
        <div class="card">
            <div class="card-h">Varian {{ $label }} @if ($label === 'A')<span style="color:var(--err)">*</span>@endif</div>
            <div class="card-b">
                <textarea class="input" id="v-{{ $label }}" name="variants[{{ $label }}]" rows="7" maxlength="{{ config('whatsapp.max_text_length') }}"
                    placeholder="Halo {{ $wrap('first_name') }}, ada informasi terbaru untuk Anda…" @if ($label === 'A') required data-preview-src="#pv-A" data-preview-url="{{ route('templates.preview') }}" @endif>{{ old('variants.'.$label, $variants[$label] ?? '') }}</textarea>
                @error('variants.'.$label)<div class="error-text">{{ $message }}</div>@enderror
            </div>
        </div>
    @endforeach
</div>
<div class="card" style="margin-top:10px">
    <div class="card-h">Lampiran foto / file <span class="muted" style="font-weight:400;text-transform:none;letter-spacing:0">(opsional)</span></div>
    <div class="card-b">
        @if ($template->media_type)
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
                @if ($template->media_type === 'image')<img class="campaign-media-thumb" src="{{ route('templates.media', $template) }}" alt="Lampiran">@else<span style="font-size:30px">📄</span>@endif
                <div><b>{{ $template->media_name }}</b><div class="muted" style="font-size:12px">{{ \App\Support\MediaFile::LABELS[$template->media_type] ?? $template->media_type }} · {{ number_format(($template->media_size ?? 0) / 1024, 0, ',', '.') }} KB</div>
                    <label class="check" style="margin-top:4px"><input type="checkbox" name="remove_media" value="1"> Hapus lampiran ini</label></div>
            </div>
            <div class="hint" style="margin-bottom:6px">Pilih berkas baru di bawah untuk mengganti.</div>
        @endif
        @include('partials.dropzone', ['name' => 'file', 'id' => 'template-file'])
        <div class="hint" style="margin-top:6px">Foto / PDF / dokumen / audio / video (maks 16 MB) ikut terkirim setiap kali template ini dipakai broadcast; isi varian menjadi keterangannya.</div>
        @error('file')<div class="error-text">{{ $message }}</div>@enderror
    </div>
</div>
<div style="margin-top:10px">
    <button class="btn btn-primary" data-once>Simpan template</button>
    <a class="btn" href="{{ route('templates.index') }}">Batal</a>
</div>
</form>
@endsection
