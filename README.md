# ALMARA Gateway WA

Aplikasi **sederhana untuk mengirim pesan WhatsApp** — self-hosted, dibangun mengikuti *ALMARA Gateway WA Master Blueprint v1.1* (tahap MVP Foundation + Gateway runtime + Messaging dasar).

> Connect. Automate. Sell. Grow.  
> Developer: **@2026-Alifiandra**

📘 **Panduan pemasangan lengkap (Windows/Laragon & server Ubuntu, update, backup, pemecahan masalah): [`docs/INSTALASI.md`](docs/INSTALASI.md)**  
📗 Dokumentasi API: [`docs/api/README.md`](docs/api/README.md) · Postman: [`docs/postman/`](docs/postman/) · Engine Scan QR: [`engine/README.md`](engine/README.md)

## Baru di versi 1.0.0 — Siap disewakan (multi-penyewa)

| Fitur | Keterangan |
|---|---|
| Multi-penyewa | Satu server untuk banyak akun usaha; data tiap penyewa terpisah total (nomor WA, kontak, chat, broadcast, pengaturan, API key). |
| Panel super-admin | Menu **Platform → Penyewa**: buat penyewa + akun pemiliknya, paket & batas per penyewa, masa sewa, catat pembayaran, tangguhkan, hapus, "masuk ke akun" penyewa untuk membantu. |
| Tagihan otomatis | Pengingat WhatsApp H-7/H-3/H-1/hari H ke penyewa dari nomor utama Anda; lewat masa sewa + 3 hari → otomatis paket Gratis. |
| Pendaftaran mandiri | Opsional (`SIGNUP_ENABLED=true`): calon penyewa daftar sendiri di `/daftar` dengan paket Gratis. |

Panduan: [`docs/PENYEWAAN.md`](docs/PENYEWAAN.md).

## Versi 0.9.0 — Paket Gratis / Pro (lisensi)

Untuk aplikasi yang disewakan. Tanpa lisensi: paket **Gratis** (1 nomor, 100 pesan/hari, tanpa broadcast & AI, pesan diberi watermark). Kode lisensi **Pro** ditandatangani digital dengan kunci privat pemilik aplikasi dan diaktifkan di menu **Sistem → Lisensi**. Panduan pemilik: [`docs/LISENSI.md`](docs/LISENSI.md).

## Versi 0.8.0 — Anti-blokir broadcast (Scan QR)

| Fitur | Keterangan |
|---|---|
| Jeda acak + mengetik | Pesan broadcast masuk antrean engine dan dikirim satu per satu dengan jeda acak (default 8–25 detik) serta status "sedang mengetik…". Balasan Inbox tetap langsung terkirim. |
| Batas harian per nomor | Default 300 broadcast / nomor / hari; sisa penerima otomatis dilanjutkan besok. |
| Pemanasan nomor baru | Hari 1–3: 30, 4–7: 60, 8–14: 120, 15–21: 200 pesan/hari, setelah itu mengikuti batas harian. |
| Pantauan | Kuota harian tampil di menu Perangkat & detail campaign. Atur di **Pengaturan → Anti-blokir**. |

## Versi 0.7.0 — Broadcast anti-seragam & rombongan

| Fitur | Keterangan |
|---|---|
| Lampiran di template | Template bisa menyimpan foto / PDF / dokumen / audio / video; ikut terkirim saat broadcast (lampiran campaign tetap diutamakan bila diisi). |
| Multi-template acak | Satu campaign boleh memakai beberapa / semua template. Setiap penerima mendapat varian acak dari semua template terpilih, dibagi merata. |
| Spintax | Tulis `{Halo\|Hai\|Selamat siang}` di template — tiap penerima mendapat pilihan kata acak. |
| Rombongan | Menu **Growth → Rombongan**: kelompok penerima per peruntukan; isi dari tempel nomor, tag, anggota grup WA, rombongan lain, atau aksi massal Kontak. Dipilih di form broadcast. |
| Duplikat campaign | Tombol **Duplikat** di detail campaign untuk mengirim ulang ke rombongan lain. |

## Versi 0.6.0 — Kontak HP, riwayat chat, grup, broadcast berlampiran

Khusus perangkat **Scan QR**:

| Fitur | Keterangan |
|---|---|
| Sinkron kontak HP | Tombol **Kontak → Sinkron dari HP** / **Perangkat → Sinkron kontak**. Kontak baru bersumber `wa-sync`, izin tetap "belum ada izin"; nama yang sudah diisi tim tidak ditimpa. |
| Riwayat chat | Saat scan QR, chat terbaru dari HP ikut masuk ke Inbox (tanpa memicu auto-reply, AI, notifikasi). |
| Grup WA | Menu **Grup WA**: daftar grup & anggota, buka chat grup di Inbox (tab *Grup*), balas / kirim teks & lampiran ke grup, simpan anggota ke Kontak (+tag). API: `to` = ID grup `…@g.us`. |
| Broadcast berlampiran | Form campaign punya **Lampiran** (gambar / PDF / dokumen / audio / video, maks 16 MB); isi template menjadi keterangan. Berlaku untuk semua provider. |

Auto-reply, AI Agent, dan STOP/START sengaja tidak aktif di chat grup.

## Versi 0.5.0 — AI Agent

AI menjawab chat pelanggan sendiri memakai **OpenRouter** (model gratis `openrouter/free` secara default, bisa diganti model berbayar).

| Fitur | Keterangan |
|---|---|
| Balas otomatis | Chat masuk yang tidak cocok dengan Auto-Reply dijawab AI berdasarkan **Basis Pengetahuan** (FAQ, produk, harga, jam buka, ongkir, dll). AI dilarang mengarang di luar data. |
| Serah ke CS | Bila AI tidak tahu, pelanggan komplain, atau mengetik kata kunci (cs/admin/operator…) → pesan "disambungkan ke CS", percakapan diberi prioritas tinggi, CS mendapat notifikasi, AI berhenti di chat itu 12 jam. |
| Kendali | AI otomatis berhenti saat CS membalas (default 60 menit), bisa dimatikan per percakapan dari Inbox, mode: selalu / di luar jam kerja / hanya chat belum ditugaskan. |
| Batas & biaya | Batas pemanggilan per hari & per kontak per jam, log pemakaian (token, durasi, error). Kontak opt-out tidak dijawab. |
| Copilot | Tombol **✨ Saran AI** di Inbox menyusun draf balasan untuk CS (tidak terkirim otomatis). |
| Uji AI | Kotak chat uji di menu AI Agent tanpa mengirim WhatsApp. |

Cara aktifkan: daftar di openrouter.ai → **Keys → Create Key** → menu **AI Agent** → tempel key, centang *Aktifkan AI Agent*, simpan → isi **Basis pengetahuan** → coba di **Uji AI**.
Kuota model gratis OpenRouter: ±50 permintaan/hari tanpa saldo, ±1.000/hari setelah isi saldo sekali $10 (dapat berubah).

## Baru di versi 0.4.0

| Area | Isi |
|---|---|
| **Pesan media** | Kirim gambar, dokumen (PDF/Office/CSV/ZIP), audio, video — dari Kirim Pesan, Inbox (📎 Lampiran), dan API `POST /api/v1/messages/media`. Media masuk dari Meta & Scan QR tersimpan dan tampil di Inbox. Maks. 16 MB, disimpan privat. |
| **Ticketing / Helpdesk** | Tiket dari chat atau manual, nomor otomatis `TCK-000001`, kategori, prioritas + SLA (atur di Pengaturan), penugasan, catatan & timeline, eskalasi otomatis bila lewat SLA, info ke pelanggan via WhatsApp, API. |
| **Smart Link** | Link klik-ke-chat `/l/slug` dengan pesan otomatis, QR siap unduh, UTM, tag otomatis untuk lead, kedaluwarsa, statistik klik → chat → konversi. |
| **Auto-Reply** | Balasan otomatis berdasarkan kata kunci (mengandung / sama persis / diawali), sambutan kontak baru, di luar jam kerja; cooldown per kontak, prioritas, tag otomatis, uji kata kunci. |

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

**Belum termasuk** (fase blueprint berikutnya): portal lisensi Free/Pro & pembayaran, Smart Voice, Workflow builder visual, Commerce, Flutter, SaaS multi-tenant, RAG berbasis embedding (saat ini pencarian kata kunci). Template resmi Meta & broadcast bermedia juga belum.

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
