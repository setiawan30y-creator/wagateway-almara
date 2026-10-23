<!doctype html>
{{-- ALMARA Gateway WA — @2026-Alifiandra --}}
<html lang="id" data-theme="paper">
<head>
    @include('partials.head')
    <title>Masuk — {{ config('app.name') }}</title>
</head>
<body>
<div class="auth">
    <div class="card">
        <div class="card-b">
            <div class="logo">
                <img src="/icons/icon-192.png" alt="">
                <div><b>ALMARA GATEWAY WA</b><span class="muted">Masuk ke panel admin</span></div>
            </div>

            @include('partials.flash', ['hideErrorSummary' => true])

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@domain.com" required autofocus autocomplete="username">
                    @error('email')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" id="password" type="password" name="password" placeholder="Password" required autocomplete="current-password">
                </div>
                <div class="field">
                    <label class="check" style="text-transform:none;font-weight:400;letter-spacing:0"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
                </div>
                <button class="btn btn-primary" style="width:100%;justify-content:center" data-once>Masuk</button>
            </form>
            <div class="footer" style="margin-top:14px"><span>Self-hosted gateway</span><span>@2026-Alifiandra</span></div>
        </div>
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v={{ config('whatsapp.version') }}"></script>
</body>
</html>
