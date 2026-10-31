@extends('layouts.app')
{{-- Detail campaign & kesehatan — @2026-Alifiandra --}}
@section('title', $campaign->name)
@section('crumb', 'Broadcast / Detail')
@section('actions')
    @php $act = fn ($a, $label, $cls = 'btn', $confirm = null) => [$a, $label, $cls, $confirm]; @endphp
    @foreach (array_filter([
        in_array($campaign->status, ['draft', 'scheduled']) ? $act('start', 'Mulai sekarang', 'btn btn-primary', 'Mulai kirim campaign ini?') : null,
        $campaign->status === 'running' ? $act('run', 'Proses batch') : null,
        $campaign->status === 'running' ? $act('pause', 'Jeda') : null,
        $campaign->status === 'paused' ? $act('resume', 'Lanjutkan', 'btn btn-primary') : null,
        ! in_array($campaign->status, ['completed', 'cancelled']) ? $act('cancel', 'Batalkan', 'btn btn-danger', 'Batalkan campaign? Penerima yang belum terkirim akan dilewati.') : null,
    ]) as [$a, $label, $cls, $confirm])
        <form method="POST" action="{{ route('campaigns.action', [$campaign, $a]) }}" class="inline" @if ($confirm) data-confirm="{{ $confirm }}" @endif>@csrf<button class="{{ $cls }}" data-once>{{ $label }}</button></form>
    @endforeach
    <a class="btn" href="{{ route('campaigns.create', ['dari' => $campaign->id]) }}" title="Salin campaign ini untuk rombongan / peruntukan lain">Duplikat</a>
    @if ($campaign->status !== 'running')
        <form method="POST" action="{{ route('campaigns.destroy', $campaign) }}" class="inline" data-confirm="Hapus campaign ini? Riwayat pesan tetap tersimpan.">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form>
    @endif
@endsection

@section('content')
@if ($campaign->pause_reason)<div class="alert alert-warning">Dijeda: {{ $campaign->pause_reason }}</div>@endif
@php $dq = $campaign->device ? \App\Support\SendingQuota::summary($campaign->device) : null; @endphp
@if ($dq && $dq['remaining'] === 0 && in_array($campaign->status, ['running', 'scheduled']))
    <div class="alert alert-warning">Batas broadcast harian nomor <b>{{ $campaign->device->name }}</b> sudah tercapai ({{ $dq['sent'] }}/{{ $dq['limit'] }}{{ $dq['warmup'] ? ', pemanasan hari ke-'.$dq['age'] : '' }}). Sisa penerima otomatis dilanjutkan besok.</div>
@endif
@if ($campaign->device?->provider === 'qr' && $campaign->status === 'running')
    <div class="hint" style="margin-bottom:8px">🛡️ Anti-blokir aktif: pesan dikirim satu per satu dengan jeda acak {{ implode('–', \App\Support\SendingQuota::delayRange()) }} detik{{ \App\Models\Setting::get('broadcast_typing') === '1' ? ' + “sedang mengetik…”' : '' }}@if ($dq) · kuota hari ini {{ $dq['sent'] }}/{{ $dq['limit'] }}@endif.</div>
@endif
@if ($quiet && in_array($campaign->status, ['running', 'scheduled']))<div class="alert alert-warning">Sedang jam tenang (quiet hours) — pengiriman otomatis menunggu.</div>@endif

<div class="grid grid-5" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Status</div><div class="v" style="font-size:16px">{{ $campaign->statusLabel() }}</div><div class="progress" style="margin-top:5px"><i style="width:{{ $stats['progress'] }}%"></i></div></div>
    <div class="card kpi"><div class="l">Terkirim / target</div><div class="v">{{ $stats['sent'] }} <small class="muted" style="font-size:12px">/ {{ $stats['total'] - $stats['skipped'] }}</small></div></div>
    <div class="card kpi"><div class="l">Delivered · Read</div><div class="v">{{ $stats['delivered'] }} <small class="muted" style="font-size:12px">· {{ $stats['read'] }}</small></div></div>
    <div class="card kpi"><div class="l">Gagal</div><div class="v" style="color:var(--err)">{{ $stats['failed'] }} <small class="muted" style="font-size:12px">{{ $stats['failed_rate'] }}%</small></div></div>
    <div class="card kpi"><div class="l">Balasan · Opt-out</div><div class="v">{{ $stats['replies'] }} <small class="muted" style="font-size:12px">{{ $stats['reply_rate'] }}% · {{ $stats['opt_outs'] }} ({{ $stats['opt_out_rate'] }}%)</small></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:10px">
    <div class="card">
        <div class="card-h">Detail</div>
        <div class="card-b">
            <dl class="kv">
                <dt>Template</dt><dd>{{ $usedTemplates->pluck('name')->implode(', ') ?: '—' }}@if ($usedTemplates->count() > 1) <span class="muted">· diacak per penerima</span>@endif</dd>
                <dt>Lampiran</dt><dd>
                    @if ($campaign->media_type)
                        📎 {{ $campaign->media_name ?: \App\Support\MediaFile::LABELS[$campaign->media_type] ?? 'media' }} <span class="muted">({{ \App\Support\MediaFile::LABELS[$campaign->media_type] ?? $campaign->media_type }}, {{ number_format(($campaign->media_size ?? 0) / 1024, 0, ',', '.') }} KB)</span>
                        @if ($campaign->media_type === 'image')<a href="{{ route('campaigns.media', $campaign) }}" target="_blank"><img class="campaign-media-thumb" src="{{ route('campaigns.media', $campaign) }}" alt="Lampiran"></a>@endif
                    @else — @endif
                    @if (in_array($campaign->status, ['draft', 'scheduled', 'paused']))
                        <details style="margin-top:6px" @if ($errors->has('file')) open @endif>
                            <summary class="btn btn-sm">{{ $campaign->media_type ? 'Ganti lampiran' : '+ Tambah foto / file' }}</summary>
                            <form method="POST" action="{{ route('campaigns.attachment', $campaign) }}" enctype="multipart/form-data" style="margin-top:6px">
                                @csrf
                                @include('partials.dropzone', ['name' => 'file', 'id' => 'campaign-file'])
                                @error('file')<div class="error-text">{{ $message }}</div>@enderror
                                <button class="btn btn-primary btn-sm" style="margin-top:6px" data-once>Simpan lampiran</button>
                            </form>
                            @if ($campaign->media_type)
                                <form method="POST" action="{{ route('campaigns.attachment', $campaign) }}" class="inline" data-confirm="Hapus lampiran campaign ini?">@csrf<input type="hidden" name="remove" value="1"><button class="btn btn-sm btn-danger" style="margin-top:4px">Hapus lampiran</button></form>
                            @endif
                        </details>
                    @endif
                </dd>
                <dt>Perangkat</dt><dd>{{ $campaign->device?->name ?? '—' }}</dd>
                <dt>Kecepatan</dt><dd>{{ $campaign->rate_per_minute }} pesan/menit</dd>
                <dt>Audience</dt><dd>
                    @if (! empty($campaign->audience['lists']))Rombongan: {{ \App\Models\RecipientList::whereIn('id', $campaign->audience['lists'])->pluck('name')->implode(', ') }} ·@endif
                    Tag: {{ \App\Models\Tag::whereIn('id', $campaign->audience['tags'] ?? [])->pluck('name')->implode(', ') ?: 'semua' }} ·
                    Tahap: {{ collect($campaign->audience['stages'] ?? [])->map(fn ($s) => \App\Models\Contact::STAGES[$s] ?? $s)->implode(', ') ?: 'semua' }}
                </dd>
                <dt>Jadwal</dt><dd>{{ $campaign->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>Mulai / selesai</dt><dd>{{ $campaign->started_at?->format('d/m/Y H:i') ?? '—' }} / {{ $campaign->finished_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>Dibuat oleh</dt><dd>{{ $campaign->creator?->name ?? 'API' }}</dd>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-h">Template & varian</div>
        <div class="table-wrap"><table class="dense">
            <thead><tr><th>Varian</th><th class="right">Penerima</th><th class="right">Terkirim</th><th class="right">Dibaca</th><th class="right">Gagal</th></tr></thead>
            <tbody>
            @foreach ($usedTemplates as $tpl) @foreach ($tpl->variants as $v)
                @php $s = $variantStats[$v->id] ?? null; @endphp
                <tr><td>@if ($usedTemplates->count() > 1)<span class="muted">{{ \Illuminate\Support\Str::limit($tpl->name, 18) }} ·</span> @endif<b>{{ $v->label }}</b> <span class="muted msg" style="display:inline-block;max-width:200px;vertical-align:bottom">{{ $v->body }}</span></td>
                    <td class="right mono">{{ $s->total ?? 0 }}</td><td class="right mono">{{ $s->sent ?? 0 }}</td><td class="right mono">{{ $s->read_count ?? 0 }}</td><td class="right mono">{{ $s->failed ?? 0 }}</td></tr>
            @endforeach @endforeach
            </tbody>
        </table></div>
        @if ($skipReasons->isNotEmpty())
            <div class="card-b" style="border-top:1px solid var(--border)"><b style="font-size:12px">Dilewati ({{ $stats['skipped'] }}):</b>
                @foreach ($skipReasons as $reason => $count)<span class="chip">{{ $reason }}: {{ $count }}</span>@endforeach</div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-h">Penerima</div>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Kontak</th><th>Nomor</th><th>Varian</th><th>Status</th><th class="hide-sm">Keterangan</th><th class="hide-sm">Waktu</th></tr></thead>
        <tbody>
        @forelse ($recipients as $r)
            <tr>
                <td>@if ($r->contact)<a href="{{ route('contacts.show', $r->contact) }}">{{ $r->contact->name ?: '—' }}</a>@else — @endif</td>
                <td class="mono">{{ $r->contact?->maskedPhone() }}</td>
                <td>{{ $r->variant ? ($usedTemplates->count() > 1 ? \Illuminate\Support\Str::limit($usedTemplates->firstWhere('id', $r->variant->template_id)?->name ?? '', 14).' · ' : '').$r->variant->label : '—' }}</td>
                <td><span class="badge st-{{ $r->message?->status ?? ($r->status === 'pending' ? 'queued' : $r->status) }}">{{ $r->message?->status ?? $r->status }}</span></td>
                <td class="hide-sm muted">{{ $r->skip_reason ?? $r->message?->error }}</td>
                <td class="hide-sm muted">{{ $r->processed_at?->format('d/m H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Daftar penerima dibuat saat campaign dimulai.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $recipients->links() }}
</div>
@endsection
