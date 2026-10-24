@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $contact->exists ? 'Ubah Kontak' : 'Tambah Kontak')
@section('crumb', 'Kontak')

@section('content')
<div class="card" style="max-width:640px">
    <div class="card-b">
        <form method="POST" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}">
            @csrf
            @if ($contact->exists) @method('PUT') @endif
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="name">Nama</label><input class="input" id="name" name="name" value="{{ old('name', $contact->name) }}" placeholder="Nama lengkap" maxlength="150"></div>
                <div class="field"><label for="phone">Nomor WhatsApp *</label><input class="input mono" id="phone" name="phone" value="{{ old('phone', $contact->phone) }}" placeholder="Contoh: 081234567890" required>@error('phone')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="email">Email</label><input class="input" id="email" type="email" name="email" value="{{ old('email', $contact->email) }}" placeholder="nama@domain.com"></div>
                <div class="field"><label for="stage">Tahap pipeline</label>
                    <select class="input" id="stage" name="stage">@foreach (\App\Models\Contact::STAGES as $k => $v)<option value="{{ $k }}" @selected(old('stage', $contact->stage ?? 'new') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="field"><label for="consent">Izin menerima pesan</label>
                    <select class="input" id="consent" name="consent">@foreach (\App\Models\Contact::CONSENTS as $k => $v)<option value="{{ $k }}" @selected(old('consent', $contact->consent ?? 'unknown') === $k)>{{ $v }}</option>@endforeach</select>
                    <div class="hint">Broadcast hanya dikirim ke kontak opt-in.</div></div>
                <div class="field"><label for="assigned_to">Penanggung jawab</label>
                    <select class="input" id="assigned_to" name="assigned_to"><option value="">—</option>@foreach ($agents as $a)<option value="{{ $a->id }}" @selected(old('assigned_to', $contact->assigned_to) == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="tags">Tag</label><input class="input" id="tags" name="tags" value="{{ old('tags', $contact->exists ? $contact->tags->pluck('name')->implode(', ') : '') }}" placeholder="Pisahkan dengan koma, mis. vip, promo"></div>
            <div class="field"><label for="notes">Catatan</label><textarea class="input" id="notes" name="notes" rows="3" placeholder="Catatan tentang customer…">{{ old('notes', $contact->notes) }}</textarea></div>
            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ $contact->exists ? route('contacts.show', $contact) : route('contacts.index') }}">Batal</a>
        </form>
    </div>
</div>
@endsection
