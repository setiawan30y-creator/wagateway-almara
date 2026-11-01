@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'API Key')
@section('crumb', 'API Key')

@section('content')
@if (session('plain_key'))
    <div class="card" style="margin-bottom:10px;border-color:var(--ok, var(--border))">
        <div class="card-h">API key baru</div>
        <div class="card-b">
            <div class="secret"><code id="plain-key">{{ session('plain_key') }}</code><button type="button" class="btn btn-sm" data-copy="#plain-key">Salin</button></div>
            <div class="hint">Key bisa dilihat & disalin lagi kapan saja dari tabel di bawah. Token per nomor juga ada di menu <a href="{{ route('devices.index') }}">Perangkat</a> → kolom Aksi → 🔗 Integrasi.</div>
        </div>
    </div>
@endif

<div class="alert alert-success" style="margin-bottom:10px">
    Cara termudah: buka <a href="{{ route('devices.index') }}"><b>Perangkat</b></a> → kolom <b>Aksi</b> → <b>🔑 Salin token</b> (satu token per nomor WhatsApp). Halaman ini untuk key tambahan, mis. satu key per aplikasi.
</div>

<div class="card" style="margin-bottom:10px">
    <div class="card-h">Daftar API key</div>
    <div class="table-wrap">
        <table class="dense">
            <thead><tr><th>Label</th><th>API key</th><th class="hide-sm">Perangkat</th><th class="hide-sm">Terakhir dipakai</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($keys as $k)
                @php $plain = $k->isRevoked() ? null : $k->plainKey(); @endphp
                <tr>
                    <td>{{ $k->name }}</td>
                    <td class="nowrap">
                        @if ($plain)
                            <span class="token-cell"><code id="key-{{ $k->id }}" class="tok is-masked">{{ $plain }}</code>
                            <button type="button" class="btn btn-sm btn-icon" data-reveal="#key-{{ $k->id }}" title="Lihat / sembunyikan">👁</button>
                            <button type="button" class="btn btn-sm" data-copy="#key-{{ $k->id }}">Salin</button></span>
                        @else
                            <span class="mono">{{ $k->prefix }}…</span>
                            @unless ($k->isRevoked())<div class="hint">Key lama — tidak bisa ditampilkan. Buat key baru lalu cabut yang ini.</div>@endunless
                        @endif
                    </td>
                    <td class="hide-sm">{{ $k->device?->name ?? 'Nomor utama' }}</td>
                    <td class="hide-sm muted">{{ $k->last_used_at?->diffForHumans() ?? 'belum' }}</td>
                    <td>@if ($k->isRevoked())<span class="badge st-failed">dicabut</span>@else<span class="badge st-connected">aktif</span>@endif</td>
                    <td class="right">
                        @unless ($k->isRevoked())
                            <form method="POST" action="{{ route('api-keys.destroy', $k) }}" class="inline" data-confirm="Cabut API key {{ $k->name }}? Aplikasi yang memakainya akan berhenti bisa mengirim.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Cabut</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Belum ada API key.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-b" style="border-top:1px solid var(--border)">
        <form method="POST" action="{{ route('api-keys.store') }}" style="display:flex;gap:6px;flex-wrap:wrap">
            @csrf
            <input class="input" name="name" placeholder="Label, mis. Aplikasi Kasir" required maxlength="100" style="flex:1;min-width:180px">
            <select class="input" name="device_id" style="width:auto;min-width:180px" title="Nomor pengirim untuk key ini">
                <option value="">Kirim dari: nomor utama</option>
                @foreach ($devices as $d)<option value="{{ $d->id }}">Kirim dari: {{ $d->name }}{{ $d->phone_number ? ' ('.$d->phone_number.')' : '' }}</option>@endforeach
            </select>
            <button class="btn btn-primary" data-once>Buat API key</button>
        </form>
        @error('device_id')<div class="error-text">{{ $message }}</div>@enderror
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Alamat API untuk aplikasi lain</div>
        <div class="card-b stack">
            @foreach ([['Kirim pesan (target, message)', url('/api/send')], ['Kirim pesan alternatif (phone, message)', url('/api/send-message')], ['REST API lengkap (to, message)', url('/api/v1/messages/send')]] as $i => [$label, $u])
                <div><div class="integ-label">{{ $label }}</div>
                    <div class="secret"><code id="api-url-{{ $i }}">{{ $u }}</code><button type="button" class="btn btn-sm" data-copy="#api-url-{{ $i }}">Salin</button></div></div>
            @endforeach
            <div class="hint">Token dikirim di header <span class="mono">Authorization</span> (boleh tanpa "Bearer") atau parameter <span class="mono">token</span>.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-h">Contoh pemakaian</div>
        <div class="card-b stack">
            <div class="muted">Kirim pesan teks (PowerShell):</div>
<pre class="code">$headers = @{
  "Authorization"   = "Bearer API_KEY_ANDA"
  "Idempotency-Key" = [guid]::NewGuid().ToString()
}
$body = @{ to = "081234567890"; message = "Halo, ini pesan uji." } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "{{ url('/api/v1/messages/send') }}" `
  -Headers $headers -ContentType "application/json" -Body $body</pre>
            <div class="muted">cURL:</div>
<pre class="code">curl -X POST {{ url('/api/v1/messages/send') }} \
  -H "Authorization: Bearer API_KEY_ANDA" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Halo, ini pesan uji."}'</pre>
            <div class="hint">Dokumentasi lengkap: <span class="mono">docs/api/</span> (OpenAPI + Postman).</div>
        </div>
    </div>
</div>
@endsection
