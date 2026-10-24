@extends('layouts.app')
{{-- Auto-Reply — @2026-Alifiandra --}}
@section('title', 'Auto-Reply')
@section('crumb', 'Auto-Reply')
@section('actions')<a class="btn btn-primary" href="{{ route('auto-replies.create') }}">+ Aturan</a>@endsection

@section('content')
<div class="grid grid-2" style="margin-bottom:10px">
    <div class="card">
        <div class="card-h">Uji kata kunci</div>
        <div class="card-b">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <input class="input" id="ar-test" placeholder="Ketik contoh pesan pelanggan, mis. harga" style="flex:1 1 200px">
                <button class="btn" type="button" id="ar-test-btn">Uji</button>
            </div>
            <div class="hint" id="ar-test-out" style="margin-top:6px">Hasil akan tampil di sini.</div>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Cara kerja</div>
        <div class="card-b" style="font-size:12.5px">
            Satu pesan masuk dibalas maksimal satu aturan: <b>kata kunci</b> (prioritas angka terkecil dulu) → <b>sambutan</b> kontak baru → <b>di luar jam kerja</b>
            (sekarang: {{ $working ? 'jam kerja' : 'di luar jam kerja' }}, {{ $hours }} — ubah di <a href="{{ route('settings.edit') }}">Pengaturan</a>).
            Kontak opt-out tidak dibalas. Cooldown mencegah balasan berulang ke kontak yang sama.
        </div>
    </div>
</div>
<div class="card">
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Nama</th><th>Pemicu</th><th>Kata kunci</th><th class="hide-sm">Balasan</th><th class="right">Terpicu</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        @forelse ($rules as $r)
            <tr>
                <td><b>{{ $r->name }}</b> <span class="muted mono" title="Prioritas">#{{ $r->priority }}</span></td>
                <td><span class="tag">{{ \App\Models\AutoReply::TRIGGERS[$r->trigger] }}</span></td>
                <td>@if ($r->trigger === 'keyword')<span class="muted" style="font-size:11px">{{ \App\Models\AutoReply::MATCHES[$r->match_type] }}:</span> @foreach ($r->keywordList() as $k)<span class="chip">{{ $k }}</span>@endforeach @else — @endif</td>
                <td class="msg hide-sm">{{ $r->reply }}</td>
                <td class="right mono">{{ $r->hits_count }}</td>
                <td>
                    <form method="POST" action="{{ route('auto-replies.toggle', $r) }}" class="inline">@csrf<button class="btn btn-sm">{{ $r->is_active ? '● Aktif' : '○ Nonaktif' }}</button></form>
                </td>
                <td class="nowrap">
                    <a class="btn btn-sm" href="{{ route('auto-replies.edit', $r) }}">Ubah</a>
                    <form method="POST" action="{{ route('auto-replies.destroy', $r) }}" class="inline" data-confirm="Hapus aturan {{ $r->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Belum ada aturan. Contoh: kata kunci “harga, price list” → kirim daftar harga.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('ar-test-btn'), inp = document.getElementById('ar-test'), out = document.getElementById('ar-test-out');
    function run() {
        fetch(@json(route('auto-replies.test')), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ text: inp.value }) })
            .then(function (r) { return r.json(); })
            .then(function (d) { out.textContent = d.matched ? ('Cocok: ' + d.rule + ' → ' + d.reply) : 'Tidak ada aturan kata kunci yang cocok.'; });
    }
    btn.addEventListener('click', run);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); run(); } });
})();
</script>
@endpush
