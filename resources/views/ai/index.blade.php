@extends('layouts.app')
{{-- AI Agent — @2026-Alifiandra --}}
@section('title', 'AI Agent')
@section('crumb', 'AI Agent')

@section('content')
@unless (\App\Support\License::allows('ai'))
    <div class="alert alert-warning">Fitur AI Agent hanya tersedia di paket <b>Pro</b>. @can('admin')<a href="{{ route('license.show') }}">Lihat paket & aktifkan lisensi</a>@endcan</div>
@endunless
@if (! $hasKey)
    <div class="card" style="margin-bottom:10px;border-color:var(--info)">
        <div class="card-h" style="color:var(--info)">Langkah awal — dapatkan API key OpenRouter (gratis)</div>
        <div class="card-b stack" style="font-size:13px">
            <div><b>1.</b> Buka <span class="mono">openrouter.ai</span> → daftar / masuk (bisa pakai akun Google).</div>
            <div><b>2.</b> Menu <b>Keys</b> → <b>Create Key</b> → salin key (diawali <span class="mono">sk-or-</span>).</div>
            <div><b>3.</b> Tempel di kolom <b>API key</b> di bawah, centang <b>Aktifkan AI Agent</b>, lalu simpan.</div>
            <div class="muted">Model gratis (<span class="mono">openrouter/free</span>) dibatasi ±50 pesan/hari tanpa saldo, ±1.000 pesan/hari setelah isi saldo sekali $10. Batas dapat berubah sewaktu-waktu oleh OpenRouter.</div>
        </div>
    </div>
@endif

<div class="grid grid-5" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Status</div><div class="v" style="font-size:16px;color:{{ $configured ? 'var(--ok)' : 'var(--muted)' }}">{{ $configured ? '● Aktif' : '○ Nonaktif' }}</div></div>
    <div class="card kpi"><div class="l">Pemanggilan AI hari ini</div><div class="v">{{ $stats['today'] }} <small class="muted" style="font-size:12px">/ {{ $s['ai_daily_limit'] }}</small></div></div>
    <div class="card kpi"><div class="l">Dijawab AI hari ini</div><div class="v" style="color:var(--ok)">{{ $stats['replies_today'] }}</div></div>
    <div class="card kpi"><div class="l">Diserahkan ke CS · 7 hari</div><div class="v" style="color:var(--warn)">{{ $stats['handoff_week'] }}</div></div>
    <div class="card kpi"><div class="l">Error · 7 hari</div><div class="v" style="color:var(--err)">{{ $stats['errors_week'] }} <small class="muted" style="font-size:12px">±{{ number_format($stats['avg_ms'] / 1000, 1) }} dtk/jawab</small></div></div>
</div>

<div class="grid grid-2">
    <div class="stack">
        <form class="card" method="POST" action="{{ route('ai.update') }}">
            @csrf @method('PUT')
            <div class="card-h">Pengaturan</div>
            <div class="card-b">
                <div class="field"><label class="check" style="text-transform:none;letter-spacing:0;font-weight:400;color:var(--text)"><input type="checkbox" name="ai_enabled" value="1" @checked($s['ai_enabled'] === '1')> <span><b>Aktifkan AI Agent</b> — AI membalas chat masuk yang tidak cocok dengan Auto-Reply</span></label></div>
                <div class="field"><label for="ai_api_key">API key OpenRouter</label>
                    <div style="display:flex;gap:6px">
                        <input class="input mono" id="ai_api_key" type="password" name="ai_api_key" autocomplete="new-password" placeholder="{{ $hasKey ? '•••••••• tersimpan (kosongkan bila tidak diubah)' : 'sk-or-v1-…' }}" style="flex:1">
                    </div>
                    <div class="hint">Disimpan terenkripsi. @if ($hasKey)<a href="#" onclick="event.preventDefault();document.getElementById('forget-key').submit()">Hapus key</a>@endif</div></div>
                <div class="grid grid-2" style="gap:0 10px">
                    <div class="field"><label for="ai_model">Model</label>
                        <input class="input mono" id="ai_model" name="ai_model" list="ai-models" value="{{ $s['ai_model'] }}" required>
                        <datalist id="ai-models">@foreach ($models as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</datalist>
                        <div class="hint">Disarankan <span class="mono">openrouter/free</span>. Bisa diganti model berbayar kapan saja.</div></div>
                    <div class="field"><label for="ai_agent_name">Nama asisten</label><input class="input" id="ai_agent_name" name="ai_agent_name" value="{{ $s['ai_agent_name'] }}" required maxlength="60"></div>
                </div>
                <div class="field"><label for="ai_persona">Persona & gaya bicara</label><textarea class="input" id="ai_persona" name="ai_persona" rows="3" required>{{ $s['ai_persona'] }}</textarea></div>
                <div class="field"><label for="ai_rules">Aturan tambahan (satu per baris)</label><textarea class="input" id="ai_rules" name="ai_rules" rows="3" placeholder="Contoh: Selalu tawarkan bantuan lain di akhir jawaban.&#10;Jangan menjanjikan diskon.">{{ $s['ai_rules'] }}</textarea></div>
                <div class="field"><label for="ai_mode">Kapan AI menjawab</label>
                    <select class="input" id="ai_mode" name="ai_mode">
                        <option value="always" @selected($s['ai_mode'] === 'always')>Selalu (24 jam)</option>
                        <option value="outside_hours" @selected($s['ai_mode'] === 'outside_hours')>Hanya di luar jam kerja (lihat Pengaturan)</option>
                        <option value="unassigned" @selected($s['ai_mode'] === 'unassigned')>Hanya percakapan yang belum ditugaskan ke CS</option>
                    </select></div>
                <div class="field"><label for="ai_handoff_keywords">Kata kunci minta CS manusia</label><input class="input" id="ai_handoff_keywords" name="ai_handoff_keywords" value="{{ $s['ai_handoff_keywords'] }}" placeholder="cs, admin, operator"></div>
                <div class="field"><label for="ai_handoff_message">Pesan saat diserahkan ke CS</label><input class="input" id="ai_handoff_message" name="ai_handoff_message" value="{{ $s['ai_handoff_message'] }}"></div>
                <div class="grid grid-2" style="gap:0 10px">
                    <div class="field"><label>AI berhenti setelah CS membalas (menit)</label><input class="input" type="number" name="ai_pause_minutes" min="1" value="{{ $s['ai_pause_minutes'] }}" required></div>
                    <div class="field"><label>Maks. balasan AI per kontak / jam</label><input class="input" type="number" name="ai_max_per_contact_hour" min="1" value="{{ $s['ai_max_per_contact_hour'] }}" required></div>
                    <div class="field"><label>Batas pemanggilan AI / hari</label><input class="input" type="number" name="ai_daily_limit" min="1" value="{{ $s['ai_daily_limit'] }}" required><div class="hint">Sesuaikan dengan kuota OpenRouter Anda.</div></div>
                    <div class="field"><label>Kreativitas (temperature 0–1.5)</label><input class="input" type="number" step="0.1" min="0" max="1.5" name="ai_temperature" value="{{ $s['ai_temperature'] }}" required><div class="hint">Rendah = lebih konsisten.</div></div>
                </div>
                <details><summary class="muted" style="cursor:pointer;font-size:12px">Lanjutan</summary>
                    <div class="field" style="margin-top:6px"><label>URL API (kompatibel OpenAI)</label><input class="input mono" name="ai_base_url" value="{{ $s['ai_base_url'] }}" required></div>
                </details>
                <button class="btn btn-primary" data-once>Simpan pengaturan</button>
            </div>
        </form>
        <form id="forget-key" method="POST" action="{{ route('ai.forget-key') }}" class="hidden" data-confirm="Hapus API key dan matikan AI?">@csrf @method('DELETE')</form>

        <div class="card">
            <div class="card-h">Uji AI <span class="r muted">tidak mengirim WhatsApp</span></div>
            <div class="thread" id="ai-chat" style="height:260px;flex:none"><span class="day-sep">Tanyakan sesuatu seperti pelanggan, mis. “jam buka kapan kak?”</span></div>
            <form class="composer" id="ai-test-form" style="position:static">
                <textarea class="input" id="ai-q" placeholder="Ketik pertanyaan pelanggan…" data-enter-submit required></textarea>
                <button class="btn btn-primary" id="ai-send">Kirim</button>
            </form>
        </div>
    </div>

    <div class="stack">
        <div class="card" id="knowledge">
            <div class="card-h">Basis pengetahuan <span class="r muted">{{ $knowledge->count() }} item</span></div>
            <div class="card-b">
                <div class="hint" style="margin-bottom:8px">AI hanya menjawab dari data di sini. Isi FAQ, daftar produk & harga, jam buka, alamat, cara order, ongkir, kebijakan retur, dll. Satu topik per item agar jawaban akurat.</div>
                <details @if ($knowledge->isEmpty()) open @endif>
                    <summary class="btn btn-primary btn-sm">+ Tambah pengetahuan</summary>
                    <form method="POST" action="{{ route('ai.knowledge.store') }}" style="margin-top:8px">
                        @csrf
                        <div class="grid grid-2" style="gap:0 10px">
                            <div class="field"><label>Judul / topik</label><input class="input" name="title" placeholder="Contoh: Jam operasional" required></div>
                            <div class="field"><label>Kategori</label><input class="input" name="category" placeholder="FAQ / Produk / Kebijakan"></div>
                        </div>
                        <div class="field"><label>Isi</label><textarea class="input" name="content" rows="5" required placeholder="Contoh: Buka Senin–Sabtu 08.00–17.00 WIB. Minggu & tanggal merah tutup."></textarea></div>
                        <button class="btn btn-primary btn-sm" data-once>Simpan</button>
                    </form>
                </details>
            </div>
            <div class="table-wrap"><table class="dense">
                <thead><tr><th>Topik</th><th class="hide-sm">Kategori</th><th>Isi</th><th class="right">Aksi</th></tr></thead>
                <tbody>
                @forelse ($knowledge as $k)
                    <tr style="{{ $k->is_active ? '' : 'opacity:.55' }}">
                        <td><b>{{ $k->title }}</b></td>
                        <td class="hide-sm">@if ($k->category)<span class="tag">{{ $k->category }}</span>@endif</td>
                        <td class="msg" style="max-width:220px">{{ $k->content }}</td>
                        <td class="nowrap right">
                            <details style="display:inline-block;position:relative">
                                <summary class="btn btn-sm">Ubah</summary>
                                <form class="card card-b" method="POST" action="{{ route('ai.knowledge.update', $k) }}" style="position:absolute;right:0;z-index:20;width:min(420px,85vw)">
                                    @csrf @method('PUT')
                                    <div class="field"><label>Judul</label><input class="input" name="title" value="{{ $k->title }}" required></div>
                                    <div class="field"><label>Kategori</label><input class="input" name="category" value="{{ $k->category }}"></div>
                                    <div class="field"><label>Isi</label><textarea class="input" name="content" rows="6" required>{{ $k->content }}</textarea></div>
                                    <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($k->is_active)> Aktif</label>
                                    <div style="margin-top:6px"><button class="btn btn-primary btn-sm">Simpan</button></div>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('ai.knowledge.destroy', $k) }}" class="inline" data-confirm="Hapus {{ $k->title }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">Belum ada pengetahuan — AI akan banyak menyerahkan ke CS.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-h">Riwayat pemanggilan <span class="r muted">{{ number_format($stats['tokens_week'], 0, ',', '.') }} token · 7 hari</span></div>
            <div class="table-wrap"><table class="dense">
                <thead><tr><th>Waktu</th><th>Jenis</th><th>Status</th><th class="hide-sm">Model</th><th class="right">Durasi</th></tr></thead>
                <tbody>
                @forelse ($logs as $l)
                    <tr>
                        <td class="muted nowrap">{{ $l->created_at->format('d/m H:i') }}</td>
                        <td>{{ ['reply' => 'Balas', 'suggest' => 'Saran', 'test' => 'Uji'][$l->purpose] ?? $l->purpose }}</td>
                        <td><span class="badge st-{{ ['ok' => 'sent', 'handoff' => 'queued', 'error' => 'failed'][$l->status] ?? 'unknown' }}" @if ($l->error) title="{{ $l->error }}" @endif>{{ $l->status }}</span></td>
                        <td class="hide-sm mono muted" style="font-size:11px">{{ $l->model ?? '—' }}</td>
                        <td class="right mono">{{ number_format($l->latency_ms / 1000, 1) }}s</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada pemanggilan.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('ai-test-form'), q = document.getElementById('ai-q'), box = document.getElementById('ai-chat'), btn = document.getElementById('ai-send');
    var history = [];
    function bubble(text, who) {
        var d = document.createElement('div');
        d.className = 'bubble ' + (who === 'user' ? 'in' : 'out');
        var t = document.createElement('span'); t.className = 'txt'; t.textContent = text; d.appendChild(t);
        box.appendChild(d); box.scrollTop = box.scrollHeight; return d;
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = q.value.trim(); if (!text) return;
        bubble(text, 'user'); q.value = ''; btn.disabled = true;
        var wait = bubble('Mengetik…', 'ai');
        fetch(@json(route('ai.test')), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ question: text, history: history }) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                wait.querySelector('.txt').textContent = d.answer || '(kosong)';
                if (!d.ok) wait.style.borderColor = 'var(--err)';
                if (d.ok) { history.push({ role: 'user', content: text }, { role: 'assistant', content: d.answer }); }
            })
            .catch(function () { wait.querySelector('.txt').textContent = 'Gagal menghubungi server.'; })
            .finally(function () { btn.disabled = false; q.focus(); });
    });
})();
</script>
@endpush
