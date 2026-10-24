@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $webhook->exists ? 'Ubah Webhook' : 'Webhook Baru')
@section('crumb', 'Webhook')

@section('content')
<div class="card" style="max-width:620px">
    <div class="card-b">
        <form method="POST" action="{{ $webhook->exists ? route('webhooks.update', $webhook) : route('webhooks.store') }}">
            @csrf
            @if ($webhook->exists) @method('PUT') @endif
            <div class="field"><label for="name">Nama</label><input class="input" id="name" name="name" value="{{ old('name', $webhook->name) }}" placeholder="Contoh: Sistem kasir" required maxlength="100"></div>
            <div class="field"><label for="url">URL tujuan</label><input class="input mono" id="url" type="url" name="url" value="{{ old('url', $webhook->url) }}" placeholder="https://domain-anda.com/webhook/wa" required>
                @error('url')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field"><label>Event</label>
                <div class="checks" style="flex-direction:column">
                    @foreach (\App\Models\Webhook::EVENTS as $k => $v)
                        <label><input type="checkbox" name="events[]" value="{{ $k }}" @checked(in_array($k, old('events', $webhook->events ?? [])))> <span class="mono">{{ $k }}</span> — {{ $v }}</label>
                    @endforeach
                </div>
                @error('events')<div class="error-text">{{ $message }}</div>@enderror</div>
            <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $webhook->is_active ?? true))> Aktif</label></div>
            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ route('webhooks.index') }}">Batal</a>
            @unless ($webhook->exists)<div class="hint" style="margin-top:6px">Signing secret dibuat otomatis dan ditampilkan sekali setelah disimpan.</div>@endunless
        </form>
    </div>
</div>
@endsection
