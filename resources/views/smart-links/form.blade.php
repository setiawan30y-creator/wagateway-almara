@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $link->exists ? 'Ubah Smart Link' : 'Smart Link Baru')
@section('crumb', 'Smart Link')

@section('content')
<div class="card" style="max-width:720px">
    <div class="card-b">
        <form method="POST" action="{{ $link->exists ? route('smart-links.update', $link) : route('smart-links.store') }}">
            @csrf
            @if ($link->exists) @method('PUT') @endif
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="name">Nama</label><input class="input" id="name" name="name" value="{{ old('name', $link->name) }}" placeholder="Contoh: Promo Instagram" required maxlength="150"></div>
                <div class="field"><label for="slug">Slug (alamat link)</label><input class="input mono" id="slug" name="slug" value="{{ old('slug', $link->slug) }}" placeholder="otomatis dari nama, mis. promo-ig" maxlength="60">
                    <div class="hint">{{ url('/l') }}/<b>slug</b></div>@error('slug')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="device_id">Nomor tujuan dari perangkat</label>
                    <select class="input" id="device_id" name="device_id"><option value="">Perangkat utama</option>@foreach ($devices as $d)<option value="{{ $d->id }}" @selected(old('device_id', $link->device_id) == $d->id)>{{ $d->name }}{{ $d->phone_number ? ' (+'.$d->phone_number.')' : ' (nomor belum diketahui)' }}</option>@endforeach</select></div>
                <div class="field"><label for="phone">Atau nomor khusus</label><input class="input mono" id="phone" name="phone" value="{{ old('phone', $link->phone) }}" placeholder="Opsional, mis. 081234567890"></div>
            </div>
            <div class="field"><label for="message">Pesan otomatis (pre-filled)</label><textarea class="input" id="message" name="message" rows="3" placeholder="Halo, saya ingin bertanya tentang…">{{ old('message', $link->message) }}</textarea>
                <div class="hint">Kode pelacak <span class="mono">[#slug]</span> ditambahkan otomatis di akhir pesan agar chat yang masuk tercatat dari link ini.</div></div>
            <div class="grid grid-3" style="gap:0 10px">
                <div class="field"><label>UTM source</label><input class="input" name="utm_source" value="{{ old('utm_source', $link->utm_source) }}" placeholder="instagram"></div>
                <div class="field"><label>UTM medium</label><input class="input" name="utm_medium" value="{{ old('utm_medium', $link->utm_medium) }}" placeholder="bio"></div>
                <div class="field"><label>UTM campaign</label><input class="input" name="utm_campaign" value="{{ old('utm_campaign', $link->utm_campaign) }}" placeholder="promo-oktober"></div>
            </div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="tag_id">Tag otomatis untuk lead</label><select class="input" id="tag_id" name="tag_id"><option value="">—</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(old('tag_id', $link->tag_id) == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
                <div class="field"><label for="expires_at">Kedaluwarsa</label><input class="input" id="expires_at" type="datetime-local" name="expires_at" value="{{ old('expires_at', $link->expires_at?->format('Y-m-d\TH:i')) }}"></div>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $link->is_active ?? true))> Aktif</label></div>
            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ $link->exists ? route('smart-links.show', $link) : route('smart-links.index') }}">Batal</a>
        </form>
    </div>
</div>
@endsection
