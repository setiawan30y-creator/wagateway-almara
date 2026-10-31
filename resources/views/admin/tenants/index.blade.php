@extends('layouts.app')
{{-- Panel super-admin: daftar penyewa — @2026-Alifiandra --}}
@section('title', 'Penyewa')
@section('crumb', 'Platform / Penyewa')
@section('actions')<a class="btn btn-primary" href="{{ route('admin.tenants.create') }}">+ Penyewa</a>@endsection

@section('content')
<div class="grid grid-4" style="margin-bottom:10px">
    <div class="card kpi"><div class="l">Total penyewa</div><div class="v">{{ $summary['total'] }}</div></div>
    <div class="card kpi"><div class="l">Pro aktif</div><div class="v" style="color:var(--ok)">{{ $summary['pro'] }}</div></div>
    <div class="card kpi"><div class="l">Habis ≤ 7 hari</div><div class="v" style="color:var(--warn)">{{ $summary['expiring'] }}</div></div>
    <div class="card kpi"><div class="l">Pemasukan bulan ini</div><div class="v" style="font-size:18px">Rp{{ number_format($summary['revenue_month'], 0, ',', '.') }}</div></div>
</div>

<div class="card">
    <form class="filters" method="GET">
        <input class="input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama usaha / kontak / nomor…">
        <select class="input" name="filter">
            @foreach (['all' => 'Semua', 'pro' => 'Pro', 'free' => 'Gratis', 'expiring' => 'Habis ≤ 7 hari', 'suspended' => 'Ditangguhkan'] as $k => $v)<option value="{{ $k }}" @selected($filter === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn">Filter</button>
    </form>
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Usaha</th><th>Paket</th><th>Berlaku sampai</th><th class="right">Nomor</th><th class="right hide-sm">Pengguna</th><th class="right hide-sm">Pesan hari ini</th><th>Status</th><th class="right">Aksi</th></tr></thead>
        <tbody>
        @forelse ($tenants as $t)
            @php $left = $t->daysLeft(); @endphp
            <tr>
                <td><a href="{{ route('admin.tenants.show', $t) }}"><b>{{ $t->name }}</b></a>@if ($t->id === \App\Models\Tenant::defaultId()) <span class="tag">utama</span>@endif
                    @if ($t->contact_name || $t->contact_phone)<div class="muted" style="font-size:11.5px">{{ $t->contact_name }} {{ $t->contact_phone ? '· +'.$t->contact_phone : '' }}</div>@endif</td>
                <td><span class="plan-chip plan-{{ $t->plan }}" style="margin:0">{{ $t->planLabel() }}</span></td>
                <td>@if ($t->plan === 'free' || ! $t->expires_at)<span class="muted">—</span>@else{{ $t->expires_at->format('d/m/Y') }}
                    <div style="font-size:11.5px;color:{{ $left < 0 ? 'var(--err)' : ($left <= 7 ? 'var(--warn)' : 'var(--muted)') }}">{{ $left < 0 ? 'lewat '.abs($left).' hari' : ($left === 0 ? 'hari ini' : $left.' hari lagi') }}</div>@endif</td>
                <td class="right mono">{{ $devices[$t->id] ?? 0 }}{{ $t->max_devices ? '/'.$t->max_devices : '' }}</td>
                <td class="right mono hide-sm">{{ $users[$t->id] ?? 0 }}</td>
                <td class="right mono hide-sm">{{ $messagesToday[$t->id] ?? 0 }}</td>
                <td>@if ($t->isActive())<span class="badge st-connected">aktif</span>@else<span class="badge st-failed">ditangguhkan</span>@endif</td>
                <td class="right nowrap">
                    <a class="btn btn-sm" href="{{ route('admin.tenants.show', $t) }}">Kelola</a>
                    <form method="POST" action="{{ route('admin.tenants.impersonate', $t) }}" class="inline">@csrf<button class="btn btn-sm" title="Lihat aplikasi sebagai penyewa ini">Masuk</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Belum ada penyewa.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($tenants->hasPages()){{ $tenants->links() }}@endif
</div>
@endsection
