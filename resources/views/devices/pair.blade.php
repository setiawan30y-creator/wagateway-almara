@extends('layouts.app')
{{-- Scan QR / pairing perangkat — @2026-Alifiandra --}}
@section('title', 'Scan QR — '.$device->name)
@section('crumb', 'Perangkat / Scan QR')
@section('actions')
    <button class="btn" type="button" id="qr-refresh">Muat ulang QR</button>
    <a class="btn" href="{{ route('devices.index') }}">Kembali</a>
@endsection

@section('content')
@if ($engineError)
    <div class="alert alert-error">
        {{ $engineError }}<br>
        Buka PowerShell baru lalu jalankan: <span class="mono">cd C:\laragon\www\wagateway-almara\engine; npm start</span> — kemudian klik “Muat ulang QR”.
    </div>
@endif
<div class="grid grid-2">
    <div class="card">
        <div class="qr-box">
            <div class="qr" id="qr-target"><span style="color:#555">Menyiapkan QR…</span></div>
            <div id="qr-status" class="muted" style="text-align:center;min-height:18px">Menghubungi engine…</div>
            <div class="progress" style="width:288px"><i id="qr-bar" style="width:100%"></i></div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Cara menghubungkan</div>
        <div class="card-b stack">
            <div><b>1.</b> Buka <b>WhatsApp</b> di HP yang nomornya ingin dipakai.</div>
            <div><b>2.</b> Ketuk <b>⋮ / Pengaturan</b> → <b>Perangkat tertaut</b> → <b>Tautkan perangkat</b>.</div>
            <div><b>3.</b> Arahkan kamera HP ke QR di samping. QR berganti otomatis ± tiap 20 detik.</div>
            <div><b>4.</b> Setelah terhubung, halaman ini otomatis kembali ke daftar perangkat.</div>
            <div class="muted">Perangkat: <b>{{ $device->name }}</b> · sesi <span class="mono">{{ $device->credential('session') ?: 'device-'.$device->id }}</span></div>
            <div class="muted" style="font-size:12px">Jalur QR memakai WhatsApp Web (tidak resmi). Kirim hanya ke kontak yang memberi izin dan hindari pengiriman massal cepat agar nomor tidak diblokir WhatsApp.</div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var url = @json(route('devices.qr', $device));
    var back = @json(route('devices.index'));
    var target = document.getElementById('qr-target');
    var statusEl = document.getElementById('qr-status');
    var bar = document.getElementById('qr-bar');
    var lastQr = null, done = false, tick = 0;

    function render(qr) {
        if (qr === lastQr) return;
        lastQr = qr;
        target.innerHTML = '';
        if (!qr) { target.innerHTML = '<span style="color:#555">Menunggu QR dari WhatsApp…</span>'; return; }
        if (qr.indexOf('data:image') === 0) {
            var img = new Image(); img.src = qr; img.alt = 'QR untuk menautkan WhatsApp'; target.appendChild(img);
        } else {
            target.innerHTML = '<span style="color:#555">Format QR tidak dikenali dari engine.</span>';
        }
        tick = 0;
    }

    function load() {
        if (done) return;
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.connected) {
                    done = true;
                    target.innerHTML = '<div style="text-align:center;color:#1f7a55"><div style="font-size:42px">✓</div><b>Terhubung</b>' + (d.phone ? '<div class="mono" style="color:#333">+' + d.phone + '</div>' : '') + '</div>';
                    statusEl.textContent = 'Berhasil! Mengalihkan ke daftar perangkat…';
                    bar.style.width = '100%';
                    setTimeout(function () { location.href = back; }, 2500);
                    return;
                }
                render(d.qr);
                statusEl.textContent = d.message || '';
            })
            .catch(function () { statusEl.textContent = 'Gagal menghubungi server gateway.'; });
    }

    document.getElementById('qr-refresh').addEventListener('click', function () { lastQr = null; load(); });
    setInterval(function () {
        if (done) return;
        tick++;
        bar.style.width = Math.max(0, 100 - tick * 5) + '%';
        if (tick % 2 === 0) load(); // cek tiap 2 detik
    }, 1000);
    load();
})();
</script>
@endpush
