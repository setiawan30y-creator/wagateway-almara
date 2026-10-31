@extends('layouts.app')
{{-- Detail penyewa — @2026-Alifiandra --}}
@section('title', $tenant->name)
@section('crumb', 'Platform / Penyewa / Detail')
@section('actions')
    <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" class="inline">@csrf<button class="btn btn-primary">Masuk ke akun ini</button></form>
    <a class="btn" href="{{ route('admin.tenants.edit', $tenant) }}">Ubah</a>
    @if ($tenant->id !== \App\Models\Tenant::defaultId())
        <form method="POST" action="{{ route('admin.tenants.toggle', $tenant) }}" class="inline" data-confirm="{{ $tenant->isActive() ? 'Tangguhkan penyewa ini? Pengguna tidak bisa login, API & pengiriman berhenti.' : 'Aktifkan kembali penyewa ini?' }}">@csrf<button class="btn {{ $tenant->isActive() ? 'btn-danger' : '' }}">{{ $tenant->isActive() ? 'Tangguhkan' : 'Aktifkan' }}</button></form>
        @unless ($tenant->isActive())
            <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" class="inline" data-confirm="HAPUS PERMANEN penyewa {{ $tenant->name }} beserta semua kontak, pesan, dan pengaturannya?">@csrf @method('DELETE')<button class="btn btn-danger">Hapus</button></form>
        @endunless
    @endif
@endsection

@section('content')
@php $left = $tenant->daysLeft(); $lim = $plan['limits']; @endphp
<div class="grid grid-4" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Paket berlaku</div><div class="v" style="font-size:18px">{{ $plan['label'] }}</div>@if ($plan['plan'] !== $tenant->plan)<div class="hint" style="color:var(--err)">Masa sewa {{ $tenant->planLabel() }} habis</div>@endif</div>
    <div class="card kpi"><div class="l">Berlaku sampai</div><div class="v" style="font-size:18px">{{ $tenant->expires_at?->format('d/m/Y') ?? '—' }}</div>@if ($left !== null)<div class="hint">{{ $left < 0 ? 'lewat '.abs($left).' hari' : $left.' hari lagi' }}</div>@endif</div>
    <div class="card kpi"><div class="l">Nomor WhatsApp</div><div class="v">{{ $devices->where('provider', '!=', 'log')->count() }} <small class="muted" style="font-size:12px">/ {{ $lim['devices'] ?? '∞' }}</small></div></div>
    <div class="card kpi"><div class="l">Pesan hari ini · bulan ini</div><div class="v">{{ $messagesToday }} <small class="muted" style="font-size:12px">· {{ $messagesMonth }}</small></div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Catat pembayaran & perpanjang</div>
        <form class="card-b" method="POST" action="{{ route('admin.tenants.pay', $tenant) }}">
            @csrf
            <div class="grid grid-3" style="gap:0 10px">
                <div class="field"><label>Paket</label><select class="input" name="plan">@foreach (config('license.plans') as $k => $p)@continue($k === 'free')<option value="{{ $k }}" @selected($tenant->plan === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
                <div class="field"><label>Tambah hari</label><input class="input" type="number" name="days" value="30" min="1" required></div>
                <div class="field"><label>Nominal (Rp)</label><input class="input" type="number" name="amount" value="0" min="0" required></div>
            </div>
            <div class="field"><label>Keterangan</label><input class="input" name="note" placeholder="Contoh: transfer bank, bulan November" maxlength="255"></div>
            <button class="btn btn-primary" data-once>Simpan pembayaran</button>
            <div class="hint" style="margin-top:6px">Masa sewa ditambahkan dari tanggal berakhir saat ini (atau dari hari ini bila sudah lewat). Penyewa yang ditangguhkan otomatis diaktifkan kembali.</div>
        </form>
    </div>
    <div class="card">
        <div class="card-h">Data penyewa</div>
        <div class="card-b">
            <dl class="kv">
                <dt>Kontak</dt><dd>{{ $tenant->contact_name ?: '—' }}</dd>
                <dt>WhatsApp</dt><dd class="mono">{{ $tenant->contact_phone ? '+'.$tenant->contact_phone : '— (pengingat tidak dikirim)' }}</dd>
                <dt>Status</dt><dd>{{ \App\Models\Tenant::STATUSES[$tenant->status] ?? $tenant->status }}</dd>
                <dt>Maks pesan/hari</dt><dd>{{ $lim['daily_messages'] ? number_format($lim['daily_messages'], 0, ',', '.') : 'Tanpa batas' }}</dd>
                <dt>ID lisensi</dt><dd class="mono">{{ $tenant->license_id ?? '—' }}</dd>
                <dt>Terdaftar</dt><dd>{{ $tenant->created_at?->format('d/m/Y') }}</dd>
            </dl>
            @if ($tenant->notes)<div class="hint" style="margin-top:6px;white-space:pre-wrap">{{ $tenant->notes }}</div>@endif
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:10px">
    <div class="card">
        <div class="card-h">Pengguna ({{ $users->count() }})</div>
        <div class="table-wrap"><table class="dense">
            <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th class="hide-sm">Login terakhir</th></tr></thead>
            <tbody>@foreach ($users as $u)<tr><td>{{ $u->name }} @unless ($u->is_active)<span class="tag">nonaktif</span>@endunless</td><td>{{ $u->email }}</td><td>{{ \App\Models\User::ROLES[$u->role] ?? $u->role }}</td><td class="hide-sm muted">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td></tr>@endforeach</tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-h">Nomor WhatsApp</div>
        <div class="table-wrap"><table class="dense">
            <thead><tr><th>Nama</th><th>Provider</th><th>Nomor</th><th>Status</th></tr></thead>
            <tbody>@forelse ($devices as $d)<tr><td>{{ $d->name }}</td><td>{{ $d->providerLabel() }}</td><td class="mono">{{ $d->phone_number ?? '—' }}</td><td><span class="badge st-{{ $d->status }}">{{ $d->status }}</span></td></tr>@empty<tr><td colspan="4" class="empty">Belum ada.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</div>

<div class="card" style="margin-top:10px">
    <div class="card-h">Riwayat pembayaran</div>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Tanggal</th><th>Paket</th><th class="right">Hari</th><th class="right">Nominal</th><th>Sampai</th><th class="hide-sm">Keterangan</th><th class="hide-sm">Dicatat oleh</th></tr></thead>
        <tbody>
        @forelse ($tenant->payments as $p)
            <tr><td>{{ $p->paid_at?->format('d/m/Y H:i') }}</td><td>{{ config('license.plans.'.$p->plan.'.label') ?? $p->plan }}</td><td class="right mono">{{ $p->days }}</td><td class="right mono">Rp{{ number_format($p->amount, 0, ',', '.') }}</td><td>{{ $p->period_until?->format('d/m/Y') }}</td><td class="hide-sm">{{ $p->note }}</td><td class="hide-sm muted">{{ $p->recorder?->name }}</td></tr>
        @empty
            <tr><td colspan="7" class="empty">Belum ada pembayaran.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
