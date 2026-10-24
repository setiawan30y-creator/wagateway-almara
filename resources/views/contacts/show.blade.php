@extends('layouts.app')
{{-- Detail kontak & timeline — @2026-Alifiandra --}}
@section('title', $contact->displayName())
@section('crumb', 'Kontak / Detail')
@section('actions')
    <form method="POST" action="{{ route('inbox.start') }}" class="inline">@csrf<input type="hidden" name="contact_id" value="{{ $contact->id }}"><button class="btn btn-primary">@include('partials.icon', ['n' => 'inbox']) Buka chat</button></form>
    <a class="btn" href="{{ route('tickets.create', ['contact' => $contact->id]) }}">+ Tiket</a>
    <a class="btn" href="{{ route('contacts.edit', $contact) }}">Ubah</a>
    @can('admin')
        <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="inline" data-confirm="Hapus kontak ini beserta percakapannya? Riwayat pesan tetap tersimpan.">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form>
    @endcan
@endsection

@section('content')
<div class="grid grid-2">
    <div class="stack">
        <div class="card">
            <div class="card-h">Profil</div>
            <div class="card-b">
                <dl class="kv">
                    <dt>Nama</dt><dd>{{ $contact->name ?: '—' }}</dd>
                    <dt>WhatsApp</dt><dd class="mono">+{{ $contact->phone }}</dd>
                    <dt>Email</dt><dd>{{ $contact->email ?: '—' }}</dd>
                    <dt>Tahap</dt><dd><span class="tag">{{ $contact->stageLabel() }}</span></dd>
                    <dt>Tag</dt><dd>@forelse ($contact->tags as $t)<span class="chip">{{ $t->name }}</span>@empty — @endforelse</dd>
                    <dt>Izin</dt><dd class="consent-{{ $contact->consent }}">{{ \App\Models\Contact::CONSENTS[$contact->consent] }} <span class="muted">{{ $contact->consent_at?->format('d/m/Y H:i') }}</span></dd>
                    <dt>Penanggung jawab</dt><dd>{{ $contact->assignee?->name ?? '—' }}</dd>
                    <dt>Sumber</dt><dd>{{ $contact->source ?? '—' }}</dd>
                    <dt>Pesan masuk / keluar</dt><dd>{{ $stats['in'] }} / {{ $stats['out'] }} · {{ $stats['campaigns'] }} campaign</dd>
                    <dt>Dibuat</dt><dd>{{ $contact->created_at->format('d/m/Y H:i') }}</dd>
                </dl>
                @if ($contact->notes)<div class="note" style="margin-top:8px">{{ $contact->notes }}</div>@endif
            </div>
        </div>
        <div class="card">
            <div class="card-h">Riwayat izin (consent)</div>
            <div class="table-wrap"><table class="dense"><tbody>
                @forelse ($contact->consentEvents as $e)
                    <tr><td class="consent-{{ $e->consent }}">{{ \App\Models\Contact::CONSENTS[$e->consent] ?? $e->consent }}</td><td>{{ $e->source }}</td><td class="muted hide-sm">{{ $e->note }}</td><td class="muted nowrap">{{ $e->created_at->format('d/m/y H:i') }}{{ $e->user ? ' · '.$e->user->name : '' }}</td></tr>
                @empty
                    <tr><td class="empty">Belum ada perubahan izin.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Timeline pesan <span class="r muted">30 terakhir</span></div>
        <div class="table-wrap"><table class="dense"><tbody>
            @forelse ($messages as $m)
                <tr>
                    <td class="nowrap muted">{{ $m->created_at->format('d/m H:i') }}</td>
                    <td><span class="tag">{{ $m->direction === 'in' ? 'masuk' : ($m->campaign_id ? 'broadcast' : 'keluar') }}</span></td>
                    <td class="msg">{{ $m->body }}</td>
                    <td>@if ($m->direction === 'out')<span class="badge st-{{ $m->status }}">{{ $m->status }}</span>@endif</td>
                </tr>
            @empty
                <tr><td class="empty">Belum ada pesan.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
