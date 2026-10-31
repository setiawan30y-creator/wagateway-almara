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
            ['groups.index', 'Grup WA', 'group', 'groups.*', false],
            ['tickets.index', 'Tiket', 'ticket', 'tickets.*', false, $navCounts['tickets'] ?? 0],
            ['send.create', 'Kirim Pesan', 'send', 'send.*', false],
            ['messages.index', 'Riwayat', 'list', 'messages.*', false],
        ],
        'Growth' => [
            ['campaigns.index', 'Broadcast', 'broadcast', 'campaigns.*', true],
            ['lists.index', 'Rombongan', 'contacts', 'lists.*', false],
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
            ['license.show', 'Lisensi', 'key', 'license.*', true],
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
        @php $lic = \App\Support\License::current(); @endphp
        <span class="plan-chip plan-{{ $lic['plan'] }}" title="{{ $lic['licensee'] ? 'Lisensi: '.$lic['licensee'] : 'Belum ada lisensi' }}">{{ $lic['label'] }}</span>
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
        <a href="{{ route('docs.show') }}" class="{{ request()->routeIs('docs.*') ? 'active' : '' }}">@include('partials.icon', ['n' => 'book']) Panduan</a>
        <a href="{{ route('account.edit') }}" class="{{ request()->routeIs('account.*') ? 'active' : '' }}">@include('partials.icon', ['n' => 'user']) Akun</a>
    </nav>

    <main class="main">
        <div class="page-head">
            <div>
                <div class="crumb">ALMARA / @yield('crumb', 'Dashboard')</div>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="actions">
                <button type="button" class="btn help-btn" data-help-open aria-haspopup="dialog" aria-controls="help-drawer" title="Fungsi & cara pakai halaman ini (tekan ?)">
                    <span class="help-q">?</span><span class="help-label">Bantuan</span>
                </button>
                @yield('actions')
            </div>
        </div>

        @include('partials.flash')
        @if ($lic['warning'])
            <div class="alert alert-warning">{{ $lic['warning'] }} @can('admin')<a href="{{ route('license.show') }}">Buka Lisensi</a>@endcan</div>
        @endif
        @yield('content')

        <footer class="footer">
            <span>ALMARA Gateway WA · self-hosted · {{ now()->format('d/m/Y H:i') }} WIB</span>
            <span>@2026-Alifiandra</span>
        </footer>
    </main>
</div>

@php $help = \App\Support\PageHelp::for(request()->route()?->getName()); @endphp
<aside class="help-drawer" id="help-drawer" role="dialog" aria-modal="false" aria-labelledby="help-title" hidden>
    <div class="help-head" data-help-drag title="Geser untuk memindahkan · klik dua kali untuk kembali ke tengah">
        <div class="help-head-title">
            <span class="help-grip" aria-hidden="true"><i></i><i></i><i></i></span>
            <div><div class="crumb">Bantuan halaman <span class="help-hint">· geser untuk memindahkan</span></div><h2 id="help-title">{{ $help['judul'] }}</h2></div>
        </div>
        <button type="button" class="btn btn-sm" data-help-close aria-label="Tutup">✕</button>
    </div>
    <div class="help-body">
        @if ($help['fungsi'])
            <h3>Fungsi</h3>
            <p>{{ $help['fungsi'] }}</p>
        @endif
        @if ($help['langkah'])
            <h3>Cara pakai</h3>
            <ol>@foreach ($help['langkah'] as $step)<li>{{ $step }}</li>@endforeach</ol>
        @endif
        @if ($help['tips'])
            <h3>Perlu diketahui</h3>
            <ul class="help-tips">@foreach ($help['tips'] as $tip)<li>{{ $tip }}</li>@endforeach</ul>
        @endif
    </div>
    <div class="help-foot">
        <a class="btn btn-primary btn-sm" href="{{ route('docs.show', $help['doc']) }}">📖 Buka Panduan lengkap</a>
        <a class="btn btn-sm" href="{{ route('docs.show', 'instalasi') }}#10-pemecahan-masalah">Pemecahan masalah</a>
    </div>
</aside>

<nav class="bottom-nav" aria-label="Menu bawah">
    @foreach ($bottom as [$route, $label, $icon, $pattern])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">@include('partials.icon', ['n' => $icon]) {{ $label }}</a>
    @endforeach
    <a href="#" data-nav-toggle>@include('partials.icon', ['n' => 'menu']) Menu</a>
</nav>

<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
