@extends('layouts.app')
{{-- @2026-Alifiandra --}}
@section('title', 'API Key')
@section('crumb', 'API Key')

@section('content')
@if (session('plain_key'))
    <div class="card" style="margin-bottom:10px;border-color:var(--warn)">
        <div class="card-h" style="color:var(--warn)">API key baru — salin sekarang</div>
        <div class="card-b">
            <div class="secret"><code id="plain-key">{{ session('plain_key') }}</code><button type="button" class="btn btn-sm" data-copy="#plain-key">Salin</button></div>
            <div class="hint">Key ini hanya ditampilkan sekali. Simpan di tempat aman (mis. .env aplikasi Anda).</div>
        </div>
    </div>
@endif

<div class="grid grid-2">
    <div class="card">
        <div class="card-h">Daftar API key</div>
        <div class="table-wrap">
            <table class="dense">
                <thead><tr><th>Label</th><th>Prefix</th><th class="hide-sm">Terakhir dipakai</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($keys as $k)
                    <tr>
                        <td>{{ $k->name }}</td>
                        <td class="mono">{{ $k->prefix }}…</td>
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
                    <tr><td colspan="5" class="empty">Belum ada API key.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-b" style="border-top:1px solid var(--border)">
            <form method="POST" action="{{ route('api-keys.store') }}" style="display:flex;gap:6px;flex-wrap:wrap">
                @csrf
                <input class="input" name="name" placeholder="Label, mis. Aplikasi Kasir" required maxlength="100" style="flex:1;min-width:180px">
                <button class="btn btn-primary" data-once>Buat API key</button>
            </form>
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
