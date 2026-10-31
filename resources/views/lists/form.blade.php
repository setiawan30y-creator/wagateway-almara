@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $list->exists ? 'Ubah Rombongan' : 'Rombongan Baru')
@section('crumb', 'Rombongan')

@section('content')
<div class="card" style="max-width:560px">
    <form class="card-b" method="POST" action="{{ $list->exists ? route('lists.update', $list) : route('lists.store') }}">
        @csrf
        @if ($list->exists) @method('PUT') @endif
        <div class="field"><label for="name">Nama rombongan</label>
            <input class="input" id="name" name="name" value="{{ old('name', $list->name) }}" placeholder="Contoh: Reseller wilayah timur" required maxlength="100">
            @error('name')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="description">Keterangan / peruntukan</label>
            <input class="input" id="description" name="description" value="{{ old('description', $list->description) }}" placeholder="Contoh: info stok & harga grosir" maxlength="255"></div>
        <button class="btn btn-primary" data-once>Simpan</button>
        <a class="btn" href="{{ $list->exists ? route('lists.show', $list) : route('lists.index') }}">Batal</a>
    </form>
</div>
@endsection
