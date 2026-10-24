@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Tiket Baru')
@section('crumb', 'Tiket / Baru')

@section('content')
<div class="card" style="max-width:680px">
    <div class="card-b">
        <form method="POST" action="{{ route('tickets.store') }}">
            @csrf
            @if ($contact)
                <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                <div class="field"><label>Pelanggan</label><div><b>{{ $contact->displayName() }}</b> <span class="muted mono">+{{ $contact->phone }}</span></div></div>
            @else
                <div class="field"><label for="phone">Nomor WhatsApp pelanggan</label><input class="input mono" id="phone" name="phone" value="{{ old('phone') }}" placeholder="Contoh: 081234567890" required>
                    @error('phone')<div class="error-text">{{ $message }}</div>@enderror</div>
            @endif
            <div class="field"><label for="subject">Judul</label><input class="input" id="subject" name="subject" value="{{ old('subject') }}" placeholder="Contoh: Pesanan belum diterima" required maxlength="191"></div>
            <div class="field"><label for="description">Deskripsi</label><textarea class="input" id="description" name="description" rows="4" placeholder="Rincian keluhan / permintaan…">{{ old('description', $prefill) }}</textarea></div>
            <div class="grid grid-3" style="gap:0 10px">
                <div class="field"><label for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\Ticket::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="field"><label for="priority">Prioritas</label><select class="input" id="priority" name="priority">@foreach (\App\Models\Ticket::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', 'normal') === $k)>{{ $v }} (SLA {{ \App\Models\Ticket::slaHours($k) }} jam)</option>@endforeach</select></div>
                <div class="field"><label for="assigned_to">Ditugaskan ke</label><select class="input" id="assigned_to" name="assigned_to"><option value="">Saya / agent percakapan</option>@foreach ($agents as $a)<option value="{{ $a->id }}" @selected(old('assigned_to') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="notify_customer" value="1" @checked(old('notify_customer', true))> Kirim nomor tiket ke pelanggan via WhatsApp</label></div>
            <button class="btn btn-primary" data-once>Buat tiket</button>
            <a class="btn" href="{{ url()->previous() }}">Batal</a>
        </form>
    </div>
</div>
@endsection
