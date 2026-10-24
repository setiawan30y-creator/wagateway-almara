@extends('layouts.app')
{{-- Detail Smart Link — @2026-Alifiandra --}}
@section('title', $link->name)
@section('crumb', 'Smart Link / Detail')
@section('actions')
    <a class="btn" href="{{ route('smart-links.edit', $link) }}">Ubah</a>
    <form method="POST" action="{{ route('smart-links.destroy', $link) }}" class="inline" data-confirm="Hapus smart link ini? Link yang sudah disebar tidak akan berfungsi lagi.">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form>
@endsection

@section('content')
@unless ($link->targetPhone())<div class="alert alert-warning">Nomor tujuan belum diketahui. Hubungkan perangkat lewat Scan QR atau isi nomor khusus.</div>@endunless
<div class="grid grid-4" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Klik</div><div class="v">{{ number_format($link->clicks_count, 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Chat dimulai</div><div class="v" style="color:var(--info)">{{ number_format($link->chats_count, 0, ',', '.') }}</div></div>
    <div class="card kpi"><div class="l">Konversi (customer)</div><div class="v" style="color:var(--ok)">{{ $conversions }}</div></div>
    <div class="card kpi"><div class="l">Klik → chat</div><div class="v">{{ $link->clicks_count ? round($link->chats_count * 100 / $link->clicks_count, 1) : 0 }}%</div></div>
</div>
<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-h">Bagikan</div>
            <div class="card-b">
                <div class="secret"><code id="short-url">{{ $link->shortUrl() }}</code><button type="button" class="btn btn-sm" data-copy="#short-url">Salin</button></div>
                <div class="qr-box" style="padding:12px 0 0">
                    <div class="qr" id="link-qr" style="min-width:220px;min-height:220px"></div>
                    <a class="btn btn-sm" id="qr-download" download="smartlink-{{ $link->slug }}.png" href="#">Unduh QR (PNG)</a>
                </div>
                <dl class="kv" style="margin-top:8px">
                    <dt>Nomor tujuan</dt><dd class="mono">{{ $link->targetPhone() ? '+'.$link->targetPhone() : '—' }}</dd>
                    <dt>Pesan awal</dt><dd>{{ $link->message }} <span class="mono muted">{{ $link->refCode() }}</span></dd>
                    <dt>UTM</dt><dd class="mono">{{ collect([$link->utm_source, $link->utm_medium, $link->utm_campaign])->filter()->implode(' / ') ?: '—' }}</dd>
                    <dt>Tag otomatis</dt><dd>{{ $link->tag?->name ?? '—' }}</dd>
                    <dt>Kedaluwarsa</dt><dd>{{ $link->expires_at?->format('d/m/Y H:i') ?? 'tidak' }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-h">Klik 14 hari</div>
            @php $max = max(1, max($daily)); @endphp
            <div class="bars">
                @foreach ($daily as $d => $c)
                    <div class="b" title="{{ $d }}: {{ $c }} klik"><i class="o" style="height:{{ max(2, $c * 100 / $max) }}%"></i><small>{{ \Illuminate\Support\Carbon::parse($d)->format('d') }}</small></div>
                @endforeach
            </div>
            <div class="card-b" style="border-top:1px solid var(--border);font-size:12px">
                Perangkat: @forelse ($devices as $k => $c)<span class="chip">{{ $k ?: 'lainnya' }}: {{ $c }}</span>@empty — @endforelse
                <br>Sumber: @forelse ($referers as $k => $c)<span class="chip">{{ $k }}: {{ $c }}</span>@empty <span class="muted">langsung / tidak diketahui</span> @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-h">Lead dari link ini</div>
            <div class="table-wrap"><table class="dense">
                <thead><tr><th>Kontak</th><th>Tahap</th><th>Masuk</th></tr></thead>
                <tbody>
                @forelse ($contacts as $c)
                    <tr><td><a href="{{ route('contacts.show', $c) }}">{{ $c->displayName() }}</a></td><td><span class="tag">{{ $c->stageLabel() }}</span></td><td class="muted">{{ $c->created_at->diffForHumans() }}</td></tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada chat dari link ini.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode.min.js') }}"></script>
<script>
(function () {
    var box = document.getElementById('link-qr');
    if (!window.QRCode) { box.textContent = 'QR tidak dapat dimuat (offline).'; return; }
    new QRCode(box, { text: @json($link->shortUrl()), width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    setTimeout(function () {
        var c = box.querySelector('canvas'), dl = document.getElementById('qr-download');
        if (c) dl.href = c.toDataURL('image/png');
    }, 300);
})();
</script>
@endpush
