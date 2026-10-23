{{-- Ikon SVG garis sederhana — @2026-Alifiandra --}}
@php
$paths = [
    'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
    'send' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
    'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
    'device' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
    'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2M17 6l3 3M14 9l2 2"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    'theme' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
    'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
    'more' => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
];
@endphp
<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">{!! $paths[$n] ?? '' !!}</svg>
