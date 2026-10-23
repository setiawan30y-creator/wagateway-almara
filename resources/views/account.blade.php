@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Akun')
@section('crumb', 'Akun')

@section('content')
<div class="card" style="max-width:520px">
    <div class="card-h">Profil & password</div>
    <div class="card-b">
        <form method="POST" action="{{ route('account.update') }}">
            @csrf @method('PUT')
            <div class="field">
                <label for="name">Nama</label>
                <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" placeholder="Nama lengkap" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="nama@domain.com" required>
            </div>
            <div class="field">
                <label for="current_password">Password lama</label>
                <input class="input" id="current_password" type="password" name="current_password" placeholder="Wajib bila mengganti password" autocomplete="current-password">
            </div>
            <div class="field">
                <label for="password">Password baru</label>
                <input class="input" id="password" type="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirmation">Ulangi password baru</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" placeholder="Ulangi password baru" autocomplete="new-password">
            </div>
            <button class="btn btn-primary" data-once>Simpan</button>
        </form>
    </div>
</div>
@endsection
