@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $tenant->exists ? 'Ubah Penyewa' : 'Penyewa Baru')
@section('crumb', 'Platform / Penyewa')

@section('content')
<form method="POST" action="{{ $tenant->exists ? route('admin.tenants.update', $tenant) : route('admin.tenants.store') }}">
@csrf
@if ($tenant->exists) @method('PUT') @endif
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Akun usaha</div>
        <div class="card-b">
            <div class="field"><label for="name">Nama usaha</label><input class="input" id="name" name="name" value="{{ old('name', $tenant->name) }}" placeholder="Contoh: Nama Toko / Perusahaan" required maxlength="120">
                @error('name')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="plan">Paket</label>
                    <select class="input" id="plan" name="plan">@foreach (config('license.plans') as $k => $p)<option value="{{ $k }}" @selected(old('plan', $tenant->plan) === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
                <div class="field"><label for="expires_at">Berlaku sampai</label><input class="input" type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', $tenant->expires_at?->toDateString()) }}">
                    <div class="hint">Kosong = tanpa batas waktu.</div></div>
                <div class="field"><label for="max_devices">Maks nomor WhatsApp</label><input class="input" type="number" min="1" id="max_devices" name="max_devices" value="{{ old('max_devices', $tenant->max_devices) }}" placeholder="Ikut paket">
                    <div class="hint">Kosong = sesuai paket (Gratis {{ config('license.plans.free.devices') }}, Pro {{ config('license.plans.pro.devices') }}).</div></div>
                <div class="field"><label for="daily_messages">Maks pesan / hari</label><input class="input" type="number" min="1" id="daily_messages" name="daily_messages" value="{{ old('daily_messages', $tenant->daily_messages) }}" placeholder="Ikut paket"></div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Kontak penyewa</div>
        <div class="card-b">
            <div class="field"><label for="contact_name">Nama kontak</label><input class="input" id="contact_name" name="contact_name" value="{{ old('contact_name', $tenant->contact_name) }}" placeholder="Nama lengkap" maxlength="120"></div>
            <div class="field"><label for="contact_phone">Nomor WhatsApp kontak</label><input class="input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}" placeholder="Contoh: 081234567890" maxlength="30">
                <div class="hint">Dipakai untuk pengingat otomatis H-7, H-3, H-1 sebelum masa sewa habis.</div></div>
            <div class="field"><label for="notes">Catatan</label><textarea class="input" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $tenant->notes) }}</textarea></div>
        </div>
    </div>
</div>

@unless ($tenant->exists)
<div class="card" style="margin-top:10px">
    <div class="card-h">Login pemilik usaha</div>
    <div class="card-b grid grid-3" style="gap:0 10px">
        <div class="field"><label for="owner_name">Nama</label><input class="input" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" placeholder="Nama lengkap" required>@error('owner_name')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="owner_email">Email login</label><input class="input" type="email" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" placeholder="email@contoh.com" required>@error('owner_email')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="owner_password">Password awal</label><input class="input" id="owner_password" name="owner_password" value="{{ old('owner_password', \Illuminate\Support\Str::password(12, symbols: false)) }}" required minlength="8">@error('owner_password')<div class="error-text">{{ $message }}</div>@enderror
            <div class="hint">Kirim ke penyewa; minta diganti di menu Akun setelah login.</div></div>
    </div>
</div>
@endunless

<div style="margin-top:10px">
    <button class="btn btn-primary" data-once>Simpan</button>
    <a class="btn" href="{{ $tenant->exists ? route('admin.tenants.show', $tenant) : route('admin.tenants.index') }}">Batal</a>
</div>
</form>
@endsection
