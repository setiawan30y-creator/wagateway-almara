@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('crumb', 'Pengguna')

@section('content')
<div class="card" style="max-width:520px">
    <div class="card-b">
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" autocomplete="off">
            @csrf
            @if ($user->exists) @method('PUT') @endif
            <div class="field"><label for="name">Nama</label><input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" placeholder="Nama lengkap" required></div>
            <div class="field"><label for="email">Email</label><input class="input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="nama@domain.com" required></div>
            <div class="field"><label for="role">Role</label>
                <select class="input" id="role" name="role" @disabled($user->id === auth()->id())>
                    @foreach (\App\Models\User::ROLES as $k => $v)
                        @if ($k !== 'owner' || auth()->user()->isOwner())<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $v }}</option>@endif
                    @endforeach
                </select>
                @if ($user->id === auth()->id())<input type="hidden" name="role" value="{{ $user->role }}"><div class="hint">Anda tidak dapat mengubah role sendiri.</div>@endif
            </div>
            <div class="field"><label for="password">Password {{ $user->exists ? '(kosongkan bila tidak diubah)' : '' }}</label><input class="input" id="password" type="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password" @required(! $user->exists)></div>
            <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) @disabled($user->id === auth()->id())> Aktif</label>
                @if ($user->id === auth()->id())<input type="hidden" name="is_active" value="1">@endif</div>
            <button class="btn btn-primary" data-once>Simpan</button>
            <a class="btn" href="{{ route('users.index') }}">Batal</a>
        </form>
    </div>
</div>
@endsection
