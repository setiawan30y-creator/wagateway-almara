@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $rule->exists ? 'Ubah Auto-Reply' : 'Auto-Reply Baru')
@section('crumb', 'Auto-Reply')

@section('content')
@php $wrap = fn ($v) => str_repeat('{', 2).$v.str_repeat('}', 2); @endphp
<div class="card" style="max-width:720px">
    <div class="card-b">
        <form method="POST" action="{{ $rule->exists ? route('auto-replies.update', $rule) : route('auto-replies.store') }}">
            @csrf
            @if ($rule->exists) @method('PUT') @endif
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="name">Nama aturan</label><input class="input" id="name" name="name" value="{{ old('name', $rule->name) }}" placeholder="Contoh: Info harga" required maxlength="100"></div>
                <div class="field"><label for="trigger">Pemicu</label><select class="input" id="trigger" name="trigger">@foreach (\App\Models\AutoReply::TRIGGERS as $k => $v)<option value="{{ $k }}" @selected(old('trigger', $rule->trigger) === $k)>{{ $v }}</option>@endforeach</select></div>
            </div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="keywords">Kata kunci</label><input class="input" id="keywords" name="keywords" value="{{ old('keywords', $rule->keywords) }}" placeholder="Pisahkan dengan koma, mis. harga, price list, pricelist">
                    @error('keywords')<div class="error-text">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="match_type">Cara mencocokkan</label><select class="input" id="match_type" name="match_type">@foreach (\App\Models\AutoReply::MATCHES as $k => $v)<option value="{{ $k }}" @selected(old('match_type', $rule->match_type) === $k)>{{ $v }}</option>@endforeach</select></div>
            </div>
            <div class="field"><label for="reply">Balasan</label><textarea class="input" id="reply" name="reply" rows="5" required placeholder="Halo {{ $wrap('first_name') }}, berikut informasi yang Anda minta…">{{ old('reply', $rule->reply) }}</textarea>
                <div class="hint">Variabel: @foreach (\App\Models\Template::VARIABLES as $v)<span class="mono">{{ $wrap($v) }}</span> @endforeach</div></div>
            <div class="grid grid-2" style="gap:0 10px">
                <div class="field"><label for="device_id">Berlaku untuk perangkat</label><select class="input" id="device_id" name="device_id"><option value="">Semua perangkat</option>@foreach ($devices as $d)<option value="{{ $d->id }}" @selected(old('device_id', $rule->device_id) == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div class="field"><label for="add_tag_id">Tambahkan tag ke kontak</label><select class="input" id="add_tag_id" name="add_tag_id"><option value="">—</option>@foreach ($tags as $t)<option value="{{ $t->id }}" @selected(old('add_tag_id', $rule->add_tag_id) == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
                <div class="field"><label for="cooldown_minutes">Cooldown per kontak (menit)</label><input class="input" id="cooldown_minutes" type="number" name="cooldown_minutes" min="0" value="{{ old('cooldown_minutes', $rule->cooldown_minutes) }}" required><div class="hint">0 = selalu membalas.</div></div>
                <div class="field"><label for="priority">Prioritas</label><input class="input" id="priority" type="number" name="priority" min="1" max="999" value="{{ old('priority', $rule->priority) }}" required><div class="hint">Angka kecil diperiksa lebih dulu.</div></div>
            </div>
            <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule->is_active ?? true))> Aktif</label></div>
            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ route('auto-replies.index') }}">Batal</a>
        </form>
    </div>
</div>
@endsection
