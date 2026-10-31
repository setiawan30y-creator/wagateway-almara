# ALMARA WA Engine (Scan QR)

Layanan Node.js kecil yang menautkan nomor WhatsApp ke gateway dengan **scan barcode/QR** (seperti WhatsApp Web).
Dipanggil oleh aplikasi Laravel — jangan dibuka ke internet.

> Developer: @2026-Alifiandra

## Kebutuhan
- Node.js **20 atau lebih baru** (`node -v`). Laragon biasanya sudah menyertakan Node; bila belum, instal Node LTS.

## Menjalankan (PowerShell)

```powershell
cd C:\laragon\www\wagateway-almara\engine
npm install
Copy-Item .env.example .env
notepad .env        # isi ENGINE_TOKEN dengan teks acak panjang
npm start
```

Lalu di `.env` Laravel (folder utama) isi token yang sama:

```
WA_ENGINE_URL=http://127.0.0.1:3000
WA_ENGINE_TOKEN=<sama dengan ENGINE_TOKEN>
```

Jalankan `php artisan config:clear`, buka panel → **Perangkat → Tambah nomor — Scan QR**.

Biarkan jendela `npm start` tetap terbuka. Sesi tersimpan di `engine/storage/` sehingga saat engine dinyalakan ulang, nomor tidak perlu scan ulang.

## Endpoint (untuk referensi)

| Method | Path | Fungsi |
|---|---|---|
| GET | `/health` | Cek engine hidup (tanpa token) |
| POST | `/sessions/start` | Mulai sesi + daftarkan webhook gateway |
| GET | `/qr?session=` | QR (data URI PNG) atau status terhubung |
| GET | `/status?session=` | Status koneksi + nomor |
| POST | `/send-message` | Kirim teks `{session, to, message}` |
| POST | `/send-media` | Kirim media `{session, to, type, mime, name, caption, data(base64)}` (maks. 16 MB) |
| POST | `/sessions/logout` | Keluarkan nomor & hapus sesi |
| GET | `/contacts?session=` | Kontak hasil sinkron dari HP `[{phone, name}]` |
| POST | `/contacts/sync` | Kirim ulang semua kontak ke gateway (event `contacts`) |
| GET | `/groups?session=` | Daftar grup + anggota `[{jid, subject, size, is_admin, announce, participants}]` |

`to` pada `/send-message` & `/send-media` boleh nomor atau ID grup `…@g.us`.

Header: `Authorization: Bearer <ENGINE_TOKEN>`.
Event ke gateway: `POST {APP_URL}/api/webhooks/qr/{device}` dengan `X-Webhook-Token` (pesan masuk pribadi & grup, status terkirim/dibaca, status sesi, kontak HP per 500, riwayat chat per 200, daftar grup).
Kontak hasil sinkron disimpan di `storage/<sesi>.contacts.json` (dihapus saat logout).

## Penting
- Jalur QR memakai protokol WhatsApp Web lewat library **Baileys** (tidak resmi, bukan Meta Cloud API). WhatsApp bisa membatasi/memblokir nomor yang dipakai untuk spam. Kirim hanya ke kontak yang memberi izin; gunakan proteksi broadcast di panel.
- Satu nomor dapat ditautkan ke maksimal beberapa perangkat sesuai batas WhatsApp; HP utama harus tetap aktif sesekali.
- Folder `storage/` berisi kunci sesi — jangan dibagikan dan jangan di-commit.

## Uji

```powershell
npm test
```
