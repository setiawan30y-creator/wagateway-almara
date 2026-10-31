@extends('layouts.app')
{{-- Detail grup WhatsApp — @2026-Alifiandra --}}
@section('title', $group->subject)
@section('crumb', 'Grup WA / Detail')
@section('actions')
    <a class="btn btn-primary" href="{{ route('groups.chat', $group) }}">@include('partials.icon', ['n' => 'inbox']) Buka chat</a>
    <form method="POST" action="{{ route('groups.sync', ['device' => $group->device_id]) }}" class="inline">@csrf<button class="btn" data-once>Sinkron ulang</button></form>
@endsection

@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Kirim pesan ke grup</div>
        <div class="card-b">
            @unless ($group->canSend())
                <div class="alert alert-warning">Grup ini hanya mengizinkan admin mengirim pesan, dan nomor perangkat bukan admin.</div>
            @endunless
            <form method="POST" action="{{ route('groups.send', $group) }}" enctype="multipart/form-data">
                @csrf
                <div class="field"><label for="body">Pesan</label><textarea class="input" id="body" name="body" rows="4" maxlength="{{ config('whatsapp.max_text_length') }}" placeholder="Tulis pesan untuk grup…">{{ old('body') }}</textarea></div>
                <div class="field"><label>Lampiran (opsional)</label><input class="input" type="file" name="file" style="height:auto;padding:4px" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.mp3,.ogg,.m4a,.aac,.amr,.opus,.mp4,.3gp">
                    <div class="hint">Gambar / dokumen / audio / video, maks 16 MB. Teks pesan menjadi keterangan lampiran.</div></div>
                <button class="btn btn-primary" data-once>@include('partials.icon', ['n' => 'send']) Kirim ke grup</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Info grup</div>
        <div class="card-b">
            <dl class="kv">
                <dt>Nama</dt><dd>{{ $group->subject }}</dd>
                <dt>Perangkat</dt><dd>{{ $group->device?->name }}</dd>
                <dt>Anggota</dt><dd>{{ $group->participants_count ?: count($group->participants ?? []) }}</dd>
                <dt>Status Anda</dt><dd>{{ $group->is_admin ? 'Admin' : 'Anggota' }}</dd>
                <dt>ID grup</dt><dd class="mono" style="font-size:11.5px">{{ $group->jid }}</dd>
                <dt>Disinkron</dt><dd>{{ $group->synced_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
            @if ($group->description)<div class="hint" style="margin-top:8px;white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($group->description, 600) }}</div>@endif

            <form method="POST" action="{{ route('groups.import', $group) }}" style="margin-top:12px;border-top:1px solid var(--border);padding-top:10px">
                @csrf
                <div class="field"><label for="tag">Simpan anggota ke Kontak, beri tag (opsional)</label>
                    <input class="input" id="tag" name="tag" maxlength="50" placeholder="Contoh: grup-{{ \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($group->subject, 20, '')) }}"></div>
                <button class="btn" data-once @disabled(empty($group->participants))>Simpan {{ count($group->memberPhones()) }} anggota ke Kontak</button>
                <div class="hint">Izin (opt-in) anggota tetap “belum ada izin” — broadcast hanya terkirim ke kontak yang sudah opt-in.</div>
            </form>
        </div>
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">Anggota ({{ count($group->participants ?? []) }})</div>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Nomor</th><th>Nama di Kontak</th><th>Peran</th></tr></thead>
        <tbody>
        @forelse ($group->participants ?? [] as $p)
            @php $c = $p['phone'] ? $known->get($p['phone']) : null; @endphp
            <tr>
                <td class="mono">{{ $p['phone'] ? '+'.$p['phone'] : '(nomor disembunyikan)' }}</td>
                <td>@if ($c)<a href="{{ route('contacts.show', $c) }}">{{ $c->displayName() }}</a>@else<span class="muted">—</span>@endif</td>
                <td>@if ($p['admin'])<span class="tag">{{ $p['admin'] === 'superadmin' ? 'pembuat' : 'admin' }}</span>@else<span class="muted">anggota</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty">Daftar anggota belum diambil. Klik <b>Sinkron ulang</b>.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
