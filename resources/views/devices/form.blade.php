@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $device->exists ? 'Ubah Perangkat' : 'Tambah Perangkat')
@section('crumb', 'Perangkat')

@section('content')
<div class="grid grid-2">
<div class="card">
    <div class="card-h">{{ $device->exists ? $device->name : 'Perangkat baru' }}</div>
    <div class="card-b">
        <form method="POST" action="{{ $device->exists ? route('devices.update', $device) : route('devices.store') }}" autocomplete="off">
            @csrf
            @if ($device->exists) @method('PUT') @endif

            <div class="field">
                <label for="name">Nama perangkat</label>
                <input class="input" id="name" name="name" value="{{ old('name', $device->name) }}" placeholder="Contoh: WA Customer Service" required maxlength="100">
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="provider">Provider</label>
                <select class="input" id="provider" name="provider" data-provider-select>
                    @foreach ($providers as $key => $label)
                        <option value="{{ $key }}" @selected(old('provider', $device->provider) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="phone_number">Nomor WhatsApp (info)</label>
                <input class="input" id="phone_number" name="phone_number" value="{{ old('phone_number', $device->phone_number) }}" placeholder="Contoh: 6281234567890" maxlength="30">
            </div>

            @foreach ($fieldsByProvider as $provider => $fields)
                <div data-provider-fields="{{ $provider }}">
                    @foreach ($fields as $key => $f)
                        @php
                            $isSecret = $f['type'] === 'password';
                            $current = $device->provider === $provider ? $device->credential($key) : null;
                        @endphp
                        <div class="field">
                            <label for="cred-{{ $provider }}-{{ $key }}">{{ $f['label'] }} @if ($f['required'])<span style="color:var(--err)">*</span>@endif</label>
                            <input class="input mono" id="cred-{{ $provider }}-{{ $key }}"
                                type="{{ $isSecret ? 'password' : ($f['type'] === 'url' ? 'url' : 'text') }}"
                                name="credentials[{{ $key }}]"
                                value="{{ $isSecret ? '' : old('credentials.'.$key, $current) }}"
                                placeholder="{{ $isSecret && $current ? '•••••••• (kosongkan bila tidak diubah)' : $f['placeholder'] }}"
                                autocomplete="new-password">
                            @error('credentials.'.$key)<div class="error-text">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    @if (empty($fields))
                        <div class="hint" style="margin-bottom:10px">Provider ini tidak memerlukan kredensial.</div>
                    @endif
                </div>
            @endforeach

            <div class="field">
                <label class="check" style="text-transform:none;font-weight:400;letter-spacing:0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true))> Aktif</label>
                <label class="check" style="text-transform:none;font-weight:400;letter-spacing:0"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $device->is_default))> Jadikan perangkat utama</label>
            </div>

            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ route('devices.index') }}">Batal</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-h">Catatan provider</div>
    <div class="card-b stack">
        <div><b>Simulasi (Log)</b> — untuk uji coba alur aplikasi & API tanpa nomor sungguhan.</div>
        <div><b>Meta Cloud API</b> — isi Phone Number ID & Access Token dari Meta App Dashboard (WhatsApp → API Setup). Status pengiriman (delivered/read) masuk lewat webhook <span class="mono">{{ url('/api/webhooks/meta') }}</span>.</div>
        <div><b>QR Multi-Device</b> — hubungkan ke engine QR eksternal yang berjalan di server/jaringan privat Anda. Engine wajib menyediakan endpoint kirim, status, dan QR (lihat README).</div>
        <div class="muted">Kredensial disimpan terenkripsi memakai APP_KEY dan tidak pernah ditampilkan ulang.</div>
    </div>
</div>
</div>
@endsection
