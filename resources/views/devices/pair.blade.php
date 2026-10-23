@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'Pairing QR — '.$device->name)
@section('crumb', 'Perangkat / Pairing')
@section('actions')
    <button class="btn" type="button" id="qr-refresh">Muat ulang QR</button>
    <a class="btn" href="{{ route('devices.index') }}">Kembali</a>
@endsection

@section('content')
<div class="card">
    <div class="qr-box">
        <div class="qr" id="qr-target"><span class="muted">Memuat QR…</span></div>
        <div id="qr-status" class="muted" style="text-align:center">Menghubungi engine…</div>
        <div class="hint">Buka WhatsApp → Perangkat tertaut → Tautkan perangkat, lalu scan QR. QR diperbarui otomatis setiap <span id="qr-count">20</span> detik.</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var url = @json(route('devices.qr', $device));
    var target = document.getElementById('qr-target');
    var statusEl = document.getElementById('qr-status');
    var countEl = document.getElementById('qr-count');
    var left = 20, done = false, timer;

    function render(qr) {
        target.innerHTML = '';
        if (!qr) { target.innerHTML = '<span class="muted">QR belum tersedia</span>'; return; }
        if (qr.indexOf('data:image') === 0) {
            var img = new Image(); img.src = qr; img.alt = 'QR pairing'; target.appendChild(img);
        } else if (window.QRCode) {
            new QRCode(target, { text: qr, width: 260, height: 260, correctLevel: QRCode.CorrectLevel.M });
        } else {
            target.textContent = 'Library QR gagal dimuat.';
        }
    }

    function load() {
        if (done) return;
        statusEl.textContent = 'Memuat…';
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.connected) {
                    done = true; clearInterval(timer);
                    target.innerHTML = '<b style="color:#1f7a55;font-size:16px">✓ Terhubung</b>';
                    statusEl.textContent = d.message || 'Perangkat terhubung.';
                    return;
                }
                render(d.qr);
                statusEl.textContent = d.message || '';
            })
            .catch(function () { statusEl.textContent = 'Gagal menghubungi server.'; });
        left = 20;
    }

    document.getElementById('qr-refresh').addEventListener('click', load);
    timer = setInterval(function () {
        left -= 1; countEl.textContent = left;
        if (left <= 0) load();
    }, 1000);
    load();
})();
</script>
@endpush
