# ALMARA Gateway WA

Aplikasi **sederhana untuk mengirim pesan WhatsApp** — self-hosted, dibangun mengikuti *ALMARA Gateway WA Master Blueprint v1.1* (tahap MVP Foundation + Gateway runtime + Messaging dasar).

> Connect. Automate. Sell. Grow.  
> Developer: **@2026-Alifiandra**

## Fitur versi 0.2.0 (Fase MVP blueprint)

| Area | Isi |
|---|---|
| **Unified Inbox** | Pesan masuk (Meta / QR / simulasi), daftar percakapan + filter (open, belum dibaca, milik saya, belum ditugaskan, pending, closed), balas, balasan cepat dari template, catatan internal, assign agent, status & prioritas, peringatan jendela 24 jam Meta, auto-refresh |
| **Kontak & CRM dasar** | Kontak otomatis dari chat, tag, tahap pipeline (tampilan daftar + kanban), penanggung jawab, catatan, timeline pesan, riwayat consent, import/export CSV, aksi massal |
| **Smart Template** | Template dengan varian A/B/C, variabel `{{name}}` `{{first_name}}` `{{phone}}` `{{email}}`, pratinjau, analitik per varian |
| **Broadcast** | Campaign berdasarkan tag & tahap, draft / mulai sekarang / jadwal, progres, statistik terkirim-dibaca-gagal-balasan-opt-out, jeda / lanjut / batal |
| **Smart Broadcast Protection** | Hanya kontak opt-in, STOP/MULAI otomatis (opt-out/opt-in + balasan), proteksi duplikat, frequency cap, quiet hours, rate limit per menit, batas penerima, **auto-pause** bila gagal / opt-out melewati batas |
| **Webhook keluar** | Event `message.received`, `message.status`, `contact.opted_out`, `campaign.finished`, tanda tangan HMAC, retry, tes, kirim ulang, rotasi secret |
| **Notification Center** | Notifikasi dalam aplikasi: pesan baru, penugasan, opt-out, campaign selesai/dijeda, perangkat terputus |
| **User / Role** | Owner, Admin, Agent (CS); akses ditegakkan di server; nonaktifkan pengguna; round-robin assignment opsional |
| **Logs** | Audit log, log request API, log pengiriman webhook |
| **Dashboard & Analytics** | KPI 7 hari, grafik pesan masuk/keluar, percakapan terbaru, broadcast aktif, `GET /api/v1/analytics` |
| Gateway (v0.1) | Multi perangkat, adapter Simulasi / Meta Cloud API / QR engine, kredensial terenkripsi, kirim pesan, riwayat, API key, PWA, 3 tema |
| REST API `/api/v1` | messages, contacts, devices, templates, campaigns, webhooks, analytics — lihat `docs/api` |

**Belum termasuk** (fase blueprint berikutnya, butuh layanan/keputusan tambahan): portal lisensi Free/Pro & pembayaran, AI Agent / RAG / Copilot (OpenRouter), Smart Voice, Smart Link, Ticketing, Workflow builder, Commerce, Flutter, SaaS multi-tenant. Pesan media (gambar/dokumen) & template resmi Meta juga belum.

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

# 5. Jalankan web (jendela PowerShell 1)
php artisan serve

# 6. Jalankan scheduler untuk broadcast terjadwal (jendela PowerShell 2)
php artisan schedule:work

# 7. Engine scan QR untuk menautkan nomor WhatsApp (jendela PowerShell 3, butuh Node.js 20+)
cd C:\laragon\www\wagateway-almara\engine
npm install
Copy-Item .env.example .env   # isi ENGINE_TOKEN, samakan dengan WA_ENGINE_TOKEN di .env Laravel
npm start
```

Tambah nomor: **Perangkat → Tambah nomor — Scan QR** → isi nama → scan QR dari HP (WhatsApp → Perangkat tertaut → Tautkan perangkat). Detail: [`engine/README.md`](engine/README.md).

Sudah pernah install versi 0.1.0? Cukup ambil update lalu migrasi:

```powershell
cd C:\laragon\www\wagateway-almara
git pull
composer install
php artisan migrate
php artisan optimize:clear
```

Buka `http://127.0.0.1:8000` (atau `http://wagateway-almara.test` bila virtual host Laragon aktif — klik *Reload* Apache/Nginx di Laragon).

Login awal diambil dari `.env` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`, default `admin@example.com` / `ubah-password-ini`). **Segera ganti password** di menu *Akun*.

### Uji coba

```powershell
php artisan test
```

## Cara pakai

0. **Inbox → Simulasi pesan masuk** untuk mencoba alur tanpa nomor sungguhan (coba kirim `STOP`).
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

### WhatsApp (Scan QR) — engine bawaan
Folder `engine/` berisi engine Node.js (Baileys) untuk menautkan nomor dengan scan QR. Jalan di `127.0.0.1:3000`, diamankan `WA_ENGINE_TOKEN`. Engine lain yang kompatibel juga bisa dipakai dengan mengisi Base URL/path per perangkat. Kontrak minimal:

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
