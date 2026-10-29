<!doctype html>
{{-- ALMARA Gateway WA — @2026-Alifiandra --}}
<html lang="id" data-theme="paper">
<head>
    @include('partials.head')
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
</head>
<body>
@php
    $me = auth()->user();
    $groups = [
        'Operasional' => [
            ['dashboard', 'Dashboard', 'home', 'dashboard', false],
            ['inbox.index', 'Inbox', 'inbox', 'inbox.*', false, $navCounts['inbox'] ?? 0],
            ['contacts.index', 'Kontak & CRM', 'contacts', 'contacts.*', false],
            ['tickets.index', 'Tiket', 'ticket', 'tickets.*', false, $navCounts['tickets'] ?? 0],
            ['send.create', 'Kirim Pesan', 'send', 'send.*', false],
            ['messages.index', 'Riwayat', 'list', 'messages.*', false],
        ],
        'Growth' => [
            ['campaigns.index', 'Broadcast', 'broadcast', 'campaigns.*', true],
            ['templates.index', 'Template', 'template', 'templates.*', true],
            ['smart-links.index', 'Smart Link', 'link', 'smart-links.*', true],
            ['auto-replies.index', 'Auto-Reply', 'bot', 'auto-replies.*', true],
            ['ai.index', 'AI Agent', 'ai', 'ai.*', true],
        ],
        'Gateway' => [
            ['devices.index', 'Perangkat', 'device', 'devices.*', true],
            ['api-keys.index', 'API Key', 'key', 'api-keys.*', true],
            ['webhooks.index', 'Webhook', 'webhook', 'webhooks.*', true],
        ],
        'Sistem' => [
            ['users.index', 'Pengguna & Role', 'users', 'users.*', true],
            ['logs.index', 'Log', 'log', 'logs.*', true],
            ['settings.edit', 'Pengaturan', 'settings', 'settings.*', true],
        ],
    ];
    $bottom = [
        ['dashboard', 'Home', 'home', 'dashboard'],
        ['inbox.index', 'Inbox', 'inbox', 'inbox.*'],
        ['contacts.index', 'Kontak', 'contacts', 'contacts.*'],
        ['send.create', 'Kirim', 'send', 'send.*'],
    ];
@endphp
<header class="topbar">
    <button type="button" class="btn btn-sm menu-toggle" data-nav-toggle aria-label="Menu">@include('partials.icon', ['n' => 'menu'])</button>
    <a class="brand" href="{{ route('dashboard') }}">
        <img src="/icons/icon-192.png" alt="">
        <span>ALMARA GATEWAY WA <small>v{{ config('whatsapp.version') }}</small></span>
    </a>
    <span class="spacer"></span>
    <a class="btn btn-sm bell" href="{{ route('notifications.index') }}" title="Notifikasi">
        @include('partials.icon', ['n' => 'bell'])
        @if (($navCounts['notifications'] ?? 0) > 0)<span class="dot">{{ min(99, $navCounts['notifications']) }}</span>@endif
    </a>
    <button type="button" class="btn btn-sm" data-theme-toggle title="Ganti tema">@include('partials.icon', ['n' => 'theme']) <span data-theme-label>Tema</span></button>
    <span class="user">{{ $me->name }} · <span class="muted">{{ $me->roleLabel() }}</span></span>
    <form method="POST" action="{{ route('logout') }}" class="inline">
        @csrf
        <button class="btn btn-sm" title="Keluar">@include('partials.icon', ['n' => 'logout'])</button>
    </form>
</header>

<div class="shell">
    <nav class="sidebar" aria-label="Menu utama">
        @foreach ($groups as $group => $items)
            @php $visible = collect($items)->filter(fn ($i) => ! $i[4] || $me->isAdmin()); @endphp
            @if ($visible->isNotEmpty())
                <div class="group">{{ $group }}</div>
                @foreach ($visible as $item)
                    <a href="{{ route($item[0]) }}" class="{{ request()->routeIs($item[3]) ? 'active' : '' }}">
                        @include('partials.icon', ['n' => $item[2]]) {{ $item[1] }}
                        @if (($item[5] ?? 0) > 0)<span class="count">{{ $item[5] }}</span>@endif
                    </a>
                @endforeach
            @endif
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
    @foreach ($bottom as [$route, $label, $icon, $pattern])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">@include('partials.icon', ['n' => $icon]) {{ $label }}</a>
    @endforeach
    <a href="#" data-nav-toggle>@include('partials.icon', ['n' => 'menu']) Menu</a>
</nav>

<script src="{{ asset('js/app.js') }}?v={{ config('whatsapp.version') }}"></script>
@stack('scripts')
</body>
</html>
