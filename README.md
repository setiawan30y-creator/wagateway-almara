# ALMARA Gateway WA

Aplikasi **sederhana untuk mengirim pesan WhatsApp** — self-hosted, dibangun mengikuti *ALMARA Gateway WA Master Blueprint v1.1* (tahap MVP Foundation + Gateway runtime + Messaging dasar).

> Connect. Automate. Sell. Grow.  
> Developer: **@2026-Alifiandra**

## Fitur versi 0.1.0

| Area | Isi |
|---|---|
| Panel admin (PWA) | Login, dashboard, kirim pesan (1–20 nomor), riwayat + filter + export CSV, kirim ulang pesan gagal, akun |
| Perangkat | Multi perangkat, perangkat utama, cek koneksi, kredensial terenkripsi (APP_KEY) |
| Provider adapter | **Simulasi (Log)** untuk uji coba · **Meta Cloud API** (resmi) · **QR Multi-Device** (engine HTTP eksternal + halaman pairing QR) |
| Messaging | Antrean (Laravel Queue), retry untuk error sementara, status `queued/sent/delivered/read/failed` |
| REST API `/api/v1` | API key (hash SHA-256, tampil sekali, bisa dicabut), rate limit per key, idempotency key, envelope respons konsisten |
| Webhook | Meta Cloud API: verifikasi token + signature, update status pesan |
| PWA | `manifest.webmanifest`, ikon, service worker (hanya cache aset statis), halaman offline |
| UI | Dense Notebook UI, tema Paper / Obsidian / Midnight, responsif (bottom nav di ponsel) |
| Dokumentasi | `docs/api` (OpenAPI 3 YAML + JSON, contoh JSON) dan `docs/postman` |

Belum termasuk (roadmap blueprint): inbox, CRM, broadcast/campaign, template, portal lisensi Free/Pro, AI, Flutter.

## Kebutuhan

- PHP **8.3+** (ekstensi: mbstring, openssl, pdo_mysql, tokenizer, ctype, fileinfo, curl)
- Composer 2
- MySQL 8 / MariaDB (Laragon sudah menyediakan)
- Laravel 13

## Instalasi di Laragon (PowerShell)

```powershell
# 1. Ambil kode
cd C:\laragon\www
git clone https://github.com/setiawan30y-creator/wagateway-almara.git
cd C:\laragon\www\wagateway-almara

# 2. Dependency & konfigurasi
composer install
Copy-Item .env.example .env
php artisan key:generate

# 3. Buat database (MySQL Laragon, user root tanpa password)
& "C:\laragon\bin\mysql\$((Get-ChildItem C:\laragon\bin\mysql | Select-Object -First 1).Name)\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS wagateway_almara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Tabel + akun admin + perangkat simulasi
php artisan migrate --seed

# 5. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000` (atau `http://wagateway-almara.test` bila virtual host Laragon aktif — klik *Reload* Apache/Nginx di Laragon).

Login awal diambil dari `.env` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`, default `admin@example.com` / `ubah-password-ini`). **Segera ganti password** di menu *Akun*.

### Uji coba

```powershell
php artisan test
```

## Cara pakai

1. **Perangkat** → perangkat *Simulasi* sudah ada. Tambah perangkat **Meta Cloud API** (isi Phone Number ID + Access Token) atau **QR Multi-Device** (isi Base URL engine), lalu klik **Cek**.
2. **Kirim Pesan** → pilih perangkat, isi nomor (08xx / 628xx / +628xx), tulis pesan, kirim.
3. **Riwayat** → lihat status, detail, kirim ulang, export CSV.
4. **API Key** → buat key untuk aplikasi lain (mis. MC Almara) lalu panggil API:

```powershell
$headers = @{ "Authorization" = "Bearer API_KEY_ANDA"; "Idempotency-Key" = [guid]::NewGuid().ToString() }
$body = @{ to = "081234567890"; message = "Halo, ini pesan uji." } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:8000/api/v1/messages/send" -Headers $headers -ContentType "application/json" -Body $body
```

Detail endpoint: [`docs/api/README.md`](docs/api/README.md).

## Provider

### Meta Cloud API (resmi)
- `.env`: `META_GRAPH_VERSION`, `META_WEBHOOK_VERIFY_TOKEN`, `META_APP_SECRET`.
- Webhook URL di Meta App Dashboard: `https://domain-anda/api/webhooks/meta` (wajib HTTPS publik — gunakan domain/tunnel).
- Pesan teks bebas hanya untuk kontak yang menghubungi Anda dalam 24 jam terakhir; di luar itu wajib template yang disetujui Meta (roadmap).

### QR Multi-Device (engine eksternal)
Gateway tidak menyertakan engine QR; jalankan engine terpisah (sebaiknya di jaringan privat) yang menyediakan:

| Method | Path default | Request | Respons |
|---|---|---|---|
| POST | `/send-message` | `{"session","to","message"}` | 2xx, id di `id` / `data.id` / `key.id` |
| GET | `/status?session=` | — | `{"connected": true, "message": "..."}` |
| GET | `/qr?session=` | — | `{"qr": "<teks QR atau data:image/png;base64,...>"}` |

Path dapat diubah per perangkat; header `Authorization: Bearer <API Token Engine>` dikirim bila diisi. Sesi QR memiliki risiko pemutusan yang berbeda dengan API resmi.

## Antrean (produksi)

Default `.env.example` memakai `QUEUE_CONNECTION=sync` (pesan dikirim langsung, cocok untuk lokal). Untuk produksi:

```powershell
# .env -> QUEUE_CONNECTION=database   (atau redis)
php artisan queue:work --tries=3
```

Di Ubuntu jalankan worker dengan Supervisor (lihat blueprint bagian 40 & 42).

## Struktur penting

```
app/WhatsApp/                 Adapter provider (Contracts, Providers, ProviderManager)
app/Services/MessageService   Simpan log + dispatch job
app/Jobs/SendWhatsAppMessage  Pengiriman + retry + idempotensi job
app/Http/Controllers/Api/V1   REST API
app/Http/Middleware/AuthenticateApiKey
config/whatsapp.php           Konfigurasi gateway
docs/api, docs/postman        Dokumentasi API
public/sw.js, manifest        PWA
```

## Keamanan & kepatuhan

- Kredensial provider terenkripsi; API key disimpan sebagai hash; secret tidak pernah ditampilkan ulang atau dicatat di log.
- Aksi sensitif (buat/cabut API key, ubah/hapus perangkat, login) tercatat di tabel `audit_logs`.
- Kirim hanya ke kontak yang memberi izin (opt-in) dan hormati permintaan berhenti. Aplikasi ini bukan alat untuk mengakali sistem anti-spam.

---
© 2026 ALMARA Gateway WA · @2026-Alifiandra
