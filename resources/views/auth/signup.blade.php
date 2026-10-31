<!doctype html>
{{-- Daftar akun usaha — @2026-Alifiandra --}}
<html lang="id" data-theme="paper">
<head>
    @include('partials.head')
    <title>Daftar — {{ config('app.name') }}</title>
</head>
<body>
<div class="auth">
    <div class="card">
        <div class="card-b">
            <div class="logo">
                <img src="/icons/icon-192.png" alt="">
                <div><b>ALMARA GATEWAY WA</b><span class="muted">Daftar akun usaha — gratis</span></div>
            </div>

            @include('partials.flash', ['hideErrorSummary' => true])

            <form method="POST" action="{{ route('signup.store') }}">
                @csrf
                @foreach ([
                    ['business', 'Nama usaha', 'text', 'Contoh: Toko Sejahtera', 'organization'],
                    ['name', 'Nama Anda', 'text', 'Nama lengkap', 'name'],
                    ['phone', 'Nomor WhatsApp', 'tel', 'Contoh: 081234567890', 'tel'],
                    ['email', 'Email (untuk login)', 'email', 'nama@domain.com', 'username'],
                    ['password', 'Password (min. 8 karakter)', 'password', 'Password', 'new-password'],
                    ['password_confirmation', 'Ulangi password', 'password', 'Ulangi password', 'new-password'],
                ] as [$f, $label, $type, $ph, $ac])
                    <div class="field">
                        <label for="{{ $f }}">{{ $label }}</label>
                        <input class="input" id="{{ $f }}" type="{{ $type }}" name="{{ $f }}" value="{{ $type === 'password' ? '' : old($f) }}" placeholder="{{ $ph }}" autocomplete="{{ $ac }}" required>
                        @error($f)<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <button class="btn btn-primary" style="width:100%;justify-content:center" data-once>Daftar gratis</button>
            </form>
            <div class="hint" style="margin-top:10px">Paket Gratis: 1 nomor WhatsApp, 100 pesan/hari. Upgrade ke Pro kapan saja.</div>
            <div class="footer" style="margin-top:14px"><a href="{{ route('login') }}">Sudah punya akun? Masuk</a><span>@2026-Alifiandra</span></div>
        </div>
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
