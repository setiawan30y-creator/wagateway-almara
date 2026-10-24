@extends('layouts.app')
{{-- Unified Inbox — @2026-Alifiandra --}}
@section('title', 'Inbox')
@section('crumb', 'Inbox')
@section('actions')
    <details style="position:relative">
        <summary class="btn btn-sm">Simulasi pesan masuk</summary>
        <div class="card" style="position:absolute;right:0;top:32px;z-index:15;width:300px">
            <form class="card-b" method="POST" action="{{ route('inbox.simulate') }}">
                @csrf
                <div class="field"><label>Nomor pengirim</label><input class="input" name="from" placeholder="Contoh: 081234567890" required></div>
                <div class="field"><label>Nama profil</label><input class="input" name="name" placeholder="Nama lengkap (opsional)"></div>
                <div class="field"><label>Pesan</label><textarea class="input" name="message" rows="3" placeholder="Isi pesan masuk… (coba: STOP)" required></textarea></div>
                <button class="btn btn-primary btn-sm">Terima pesan</button>
                <div class="hint">Untuk uji alur tanpa provider sungguhan.</div>
            </form>
        </div>
    </details>
@endsection

@section('content')
<div class="card inbox {{ $conversation ? 'has-conv' : '' }}" data-poll="{{ route('inbox.poll') }}">
    {{-- Kolom 1: daftar percakapan --}}
    <div class="col-list">
        <form method="GET" action="{{ $conversation ? route('inbox.show', $conversation) : route('inbox.index') }}" style="padding:7px;border-bottom:1px solid var(--border)">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama / nomor…">
        </form>
        <div class="tabs">
            @foreach (['open' => 'Open ('.$counts['open'].')', 'unread' => 'Belum dibaca ('.$counts['unread'].')', 'mine' => 'Milik saya ('.$counts['mine'].')', 'unassigned' => 'Belum ditugaskan', 'pending' => 'Pending', 'closed' => 'Closed', 'all' => 'Semua'] as $k => $label)
                <a href="{{ route('inbox.index', ['filter' => $k, 'q' => request('q')]) }}" class="{{ $filter === $k ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="conv-list">
            @forelse ($conversations as $c)
                <a class="conv {{ $conversation && $conversation->id === $c->id ? 'active' : '' }} {{ $c->unread_count ? 'unread' : '' }}" href="{{ route('inbox.show', ['conversation' => $c, 'filter' => $filter, 'q' => request('q')]) }}">
                    <div class="top">
                        <span class="nm">{{ $c->contact->displayName() }}</span>
                        <span class="tm">{{ $c->last_message_at?->isToday() ? $c->last_message_at->format('H:i') : $c->last_message_at?->format('d/m') }}</span>
                    </div>
                    <div class="pv">
                        <span>{{ $c->last_direction === 'out' ? 'Anda: ' : '' }}{{ $c->last_message_preview ?? '—' }}</span>
                        @if ($c->priority !== 'normal')<span class="prio-{{ $c->priority }}">{{ \App\Models\Conversation::PRIORITIES[$c->priority] }}</span>@endif
                        @if ($c->unread_count)<span class="count">{{ $c->unread_count }}</span>@endif
                    </div>
                    @if ($c->assignee)<div class="pv"><span>→ {{ $c->assignee->name }}</span></div>@endif
                </a>
            @empty
                <div class="empty">Tidak ada percakapan.<br><span class="hint">Pesan masuk dari WhatsApp akan muncul di sini.</span></div>
            @endforelse
        </div>
        @if ($conversations->hasPages()){{ $conversations->links() }}@endif
    </div>

    {{-- Kolom 2: thread --}}
    <div class="col-main">
        @if ($conversation)
            @php $contact = $conversation->contact; @endphp
            <div class="thread-head">
                <a class="btn btn-sm menu-toggle" href="{{ route('inbox.index', ['filter' => $filter]) }}">‹</a>
                <div>
                    <div class="nm">{{ $contact->displayName() }}</div>
                    <div class="muted mono" style="font-size:11.5px">+{{ $contact->phone }} · <span class="consent-{{ $contact->consent }}">{{ \App\Models\Contact::CONSENTS[$contact->consent] }}</span></div>
                </div>
                <span class="spacer" style="flex:1"></span>
                <form method="POST" action="{{ route('inbox.update', $conversation) }}" class="inline" style="display:flex;gap:4px;flex-wrap:wrap">
                    @csrf @method('PATCH')
                    <select class="input" name="status" style="width:auto;height:26px" onchange="this.form.submit()">
                        @foreach (\App\Models\Conversation::STATUSES as $k => $v)<option value="{{ $k }}" @selected($conversation->status === $k)>{{ $v }}</option>@endforeach
                    </select>
                    <select class="input" name="priority" style="width:auto;height:26px" onchange="this.form.submit()">
                        @foreach (\App\Models\Conversation::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($conversation->priority === $k)>{{ $v }}</option>@endforeach
                    </select>
                </form>
            </div>

            <div class="thread">
                @php $lastDay = null; @endphp
                @forelse ($thread as $m)
                    @php $day = $m->created_at->format('d M Y'); @endphp
                    @if ($day !== $lastDay)<span class="day-sep">{{ $m->created_at->isToday() ? 'Hari ini' : $day }}</span>@php $lastDay = $day; @endphp @endif
                    <div class="bubble {{ $m->direction === 'in' ? 'in' : 'out' }}">{{ $m->body }}<span class="meta">
                        @if ($m->campaign_id)<span class="tag">broadcast</span>@endif
                        @if ($m->direction === 'out' && $m->user)<span>{{ $m->user->name }} ·</span>@endif
                        {{ $m->created_at->format('H:i') }}
                        @if ($m->direction === 'out')
                            <span class="badge st-{{ $m->status }}" @if ($m->error) title="{{ $m->error }}" @endif style="font-size:9.5px;padding:0 5px">{{ $m->status }}</span>
                        @endif
                    </span></div>
                @empty
                    <span class="day-sep">Belum ada pesan — mulai percakapan di bawah.</span>
                @endforelse
            </div>

            @if ($conversation->device?->provider === 'meta' && ! $conversation->withinServiceWindow())
                <div class="window-warn">Di luar jendela 24 jam Meta: pesan teks bebas kemungkinan ditolak. Gunakan template yang disetujui Meta.</div>
            @endif
            @if ($contact->consent === 'opted_out')
                <div class="window-warn">Kontak ini sudah berhenti berlangganan (opt-out). Balas hanya untuk keperluan layanan.</div>
            @endif

            <form class="composer" method="POST" action="{{ route('inbox.reply', $conversation) }}">
                @csrf
                <div style="display:flex;flex-direction:column;gap:4px;flex:1">
                    <textarea class="input" id="reply-body" name="body" placeholder="Tulis balasan… (Enter kirim, Shift+Enter baris baru)" data-enter-submit required maxlength="{{ config('whatsapp.max_text_length') }}"></textarea>
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                        @if ($templates->isNotEmpty())
                            <select class="input" style="width:auto;height:24px;font-size:11.5px" data-quick-reply="#reply-body">
                                <option value="">Balasan cepat…</option>
                                @foreach ($templates as $t)
                                    @foreach ($t->variants as $v)<option value="{{ \App\Models\Template::render($v->body, $contact) }}">{{ $t->name }} ({{ $v->label }})</option>@endforeach
                                @endforeach
                            </select>
                        @endif
                        @if ($devices->count() > 1)
                            <select class="input" name="device_id" style="width:auto;height:24px;font-size:11.5px" title="Kirim melalui">
                                @foreach ($devices as $d)<option value="{{ $d->id }}" @selected(($conversation->device_id ?? 0) === $d->id)>{{ $d->name }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                </div>
                <button class="btn btn-primary" data-once>@include('partials.icon', ['n' => 'send']) Kirim</button>
            </form>
        @else
            <div class="empty" style="margin:auto">Pilih percakapan di sebelah kiri.</div>
        @endif
    </div>

    {{-- Kolom 3: profil customer --}}
    <div class="col-side">
        @if ($conversation)
            <div class="side-sec">
                <h4>Customer</h4>
                <div><a href="{{ route('contacts.show', $contact) }}"><b>{{ $contact->displayName() }}</b></a></div>
                <div class="muted mono" style="font-size:12px">+{{ $contact->phone }}</div>
                @if ($contact->email)<div class="muted" style="font-size:12px">{{ $contact->email }}</div>@endif
                <div style="margin-top:5px"><span class="tag">{{ $contact->stageLabel() }}</span></div>
                <div style="margin-top:5px">@foreach ($contact->tags as $tg)<span class="chip">{{ $tg->name }}</span>@endforeach</div>
                <a class="btn btn-sm" style="margin-top:6px" href="{{ route('contacts.edit', $contact) }}">Ubah kontak</a>
            </div>
            <div class="side-sec">
                <h4>Ditugaskan ke</h4>
                <form method="POST" action="{{ route('inbox.update', $conversation) }}">
                    @csrf @method('PATCH')
                    <select class="input" name="assigned_to" onchange="this.form.submit()">
                        <option value="">— Belum ditugaskan —</option>
                        @foreach ($agents as $ag)<option value="{{ $ag->id }}" @selected($conversation->assigned_to === $ag->id)>{{ $ag->name }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="side-sec">
                <h4>Catatan internal</h4>
                <form method="POST" action="{{ route('inbox.note', $conversation) }}" style="margin-bottom:6px">
                    @csrf
                    <textarea class="input" name="body" rows="2" style="min-height:50px" placeholder="Hanya terlihat oleh tim…" required></textarea>
                    <button class="btn btn-sm" style="margin-top:4px">Simpan catatan</button>
                </form>
                @foreach ($conversation->notes as $note)
                    <div class="note">{{ $note->body }}<small>{{ $note->user?->name ?? 'Sistem' }} · {{ $note->created_at->format('d/m H:i') }}</small></div>
                @endforeach
            </div>
            @if ($contact->notes)
                <div class="side-sec"><h4>Catatan kontak</h4><div style="font-size:12px;white-space:pre-wrap">{{ $contact->notes }}</div></div>
            @endif
        @endif
    </div>
</div>
@endsection
