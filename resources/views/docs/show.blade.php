@extends('layouts.app')
{{-- Panduan / dokumentasi di dalam panel — @2026-Alifiandra --}}
@section('title', $title)
@section('crumb', 'Panduan')
@section('actions')
    <button type="button" class="btn" onclick="window.print()">Cetak / PDF</button>
@endsection

@section('content')
<div class="docs-wrap">
    <nav class="card docs-nav" aria-label="Daftar dokumen">
        <div class="card-h">Panduan</div>
        @foreach ($docs as $k => [$label])
            <a href="{{ route('docs.show', $k) }}" class="{{ $slug === $k ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <article class="card card-b doc">{!! $html !!}</article>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Tombol salin pada setiap blok perintah
    document.querySelectorAll('.doc pre').forEach(function (pre) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn-sm copy-code'; b.textContent = 'Salin';
        b.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(pre.innerText.replace(/Salin$|Tersalin$/, '').trim()).then(function () {
                b.textContent = 'Tersalin'; setTimeout(function () { b.textContent = 'Salin'; }, 1500);
            });
        });
        pre.appendChild(b);
    });
    // Tautan luar dibuka di tab baru
    document.querySelectorAll('.doc a[href^="http"]').forEach(function (a) {
        if (a.host !== location.host) { a.target = '_blank'; a.rel = 'noopener'; }
    });
})();
</script>
@endpush
