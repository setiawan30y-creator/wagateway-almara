<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1f7a55">
<meta name="author" content="@2026-Alifiandra">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="/icons/favicon-32.png">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('whatsapp.version') }}">
<script>
    (function () { try { var t = localStorage.getItem('almara-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {} })();
</script>
