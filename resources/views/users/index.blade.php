@extends('layouts.app')
{{-- Pengguna & role — @2026-Alifiandra --}}
@section('title', 'Pengguna & Role')
@section('crumb', 'Pengguna')
@section('actions')<a class="btn btn-primary" href="{{ route('users.create') }}">+ Pengguna</a>@endsection

@section('content')
<div class="grid grid-2">
<div class="card">
    <div class="table-wrap"><table class="dense">
        <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th class="hide-sm">Login terakhir</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td class="muted">{{ $u->email }}</td>
                <td><span class="tag">{{ $u->roleLabel() }}</span></td>
                <td>@if ($u->is_active)<span class="badge st-connected">aktif</span>@else<span class="badge st-failed">nonaktif</span>@endif</td>
                <td class="hide-sm muted">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                <td class="right"><a class="btn btn-sm" href="{{ route('users.edit', $u) }}">Ubah</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-h">Hak akses</div>
    <div class="card-b stack">
        <div><b>Owner</b> — semua menu, termasuk mengelola akun owner lain.</div>
        <div><b>Admin</b> — semua menu: broadcast, template, perangkat, API key, webhook, pengguna, log, pengaturan.</div>
        <div><b>Agent (CS)</b> — Dashboard, Inbox, Kontak, Kirim Pesan, Riwayat. Tidak bisa menghapus kontak.</div>
        <div class="muted">Akses ditegakkan di server (bukan hanya menu). Pengguna nonaktif otomatis keluar.</div>
    </div>
</div>
</div>
@endsection
