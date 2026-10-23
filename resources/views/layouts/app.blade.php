<!doctype html>
{{-- ALMARA Gateway WA — @2026-Alifiandra --}}
<html lang="id" data-theme="paper">
<head>
    @include('partials.head')
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
</head>
<body>
@php
    $nav = [
        ['dashboard', 'Dashboard', 'home', 'dashboard'],
        ['send.create', 'Kirim Pesan', 'send', 'send.*'],
        ['messages.index', 'Riwayat', 'list', 'messages.*'],
        ['devices.index', 'Perangkat', 'device', 'devices.*'],
        ['api-keys.index', 'API Key', 'key', 'api-keys.*'],
    ];
@endphp
<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}">
        <img src="/icons/icon-192.png" alt="">
        <span>ALMARA GATEWAY WA <small>v{{ config('whatsapp.version') }}</small></span>
    </a>
    <span class="spacer"></span>
    <button type="button" class="btn btn-sm" data-theme-toggle title="Ganti tema">@include('partials.icon', ['n' => 'theme']) <span data-theme-label>Tema</span></button>
    <span class="user">{{ auth()->user()->name }}</span>
    <form method="POST" action="{{ route('logout') }}" class="inline">
        @csrf
        <button class="btn btn-sm" title="Keluar">@include('partials.icon', ['n' => 'logout'])</button>
    </form>
</header>

<div class="shell">
    <nav class="sidebar" aria-label="Menu utama">
        @foreach ($nav as [$route, $label, $icon, $pattern])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">@include('partials.icon', ['n' => $icon]) {{ $label }}</a>
        @endforeach
        <div class="sep"></div>
        <a href="{{ route('account.edit') }}" class="{{ request()->routeIs('account.*') ? 'active' : '' }}">@include('partials.icon', ['n' => 'user']) Akun</a>
    </nav>

    <main class="main">
        <div class="page-head">
            <div>
                <div class="crumb">ALMARA / @yield('crumb', 'Dashboard')</div>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="actions">@yield('actions')</div>
        </div>

        @include('partials.flash')
        @yield('content')

        <footer class="footer">
            <span>ALMARA Gateway WA · self-hosted · {{ now()->format('d/m/Y H:i') }} WIB</span>
            <span>@2026-Alifiandra</span>
        </footer>
    </main>
</div>

<nav class="bottom-nav" aria-label="Menu bawah">
    @foreach ($nav as [$route, $label, $icon, $pattern])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">@include('partials.icon', ['n' => $icon]) {{ $route === 'send.create' ? 'Kirim' : $label }}</a>
    @endforeach
</nav>

<script src="{{ asset('js/app.js') }}?v={{ config('whatsapp.version') }}"></script>
@stack('scripts')
</body>
</html>
