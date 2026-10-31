# Menyewakan ALMARA Gateway WA (multi-penyewa)

Panduan untuk **pemilik aplikasi**. Satu server melayani banyak penyewa (akun usaha); data tiap penyewa — nomor WhatsApp, kontak, chat, broadcast, pengaturan, API key — terpisah total. @2026-Alifiandra

## Peran

| Peran | Siapa | Bisa apa |
|---|---|---|
| **Super-admin** | Anda (pemilik aplikasi) | Menu **Platform → Penyewa**: buat penyewa, atur paket & masa sewa, catat pembayaran, tangguhkan, "masuk ke akun" penyewa |
| **Owner / Admin** penyewa | Pemilik usaha penyewa | Semua menu di akun usahanya sendiri, menambah pengguna (CS) |
| **Agent** | CS penyewa | Inbox, kontak, tiket, kirim pesan |

Akun usaha **#1 "Usaha Utama"** adalah milik Anda sendiri: paket Pro tanpa batas waktu, tidak bisa ditangguhkan / dihapus, dan nomor WhatsApp utamanya dipakai untuk mengirim pengingat tagihan ke penyewa. Ganti namanya di **Penyewa → Usaha Utama → Ubah**.

## Paket

| | Gratis | Pro |
|---|---|---|
| Nomor WhatsApp | 1 | 5 (bisa diatur per penyewa) |
| Pesan keluar / hari | 100 | Tanpa batas (bisa diatur) |
| Broadcast, rombongan, AI Agent | — | ✓ |
| Watermark di pesan | Ya | Tidak |

Ubah isi paket di `config/license.php`. Batas khusus per penyewa (maks nomor, maks pesan/hari) diisi di form penyewa.

## Alur menyewakan

1. **Pasang di server** (Ubuntu) — ikuti `docs/INSTALASI.md` bagian 7. Penyewa hanya mendapat akses login lewat browser; kode program tetap di server Anda sehingga pembatasan paket tidak bisa dibobol.
2. **Tambah penyewa**: Platform → Penyewa → **+ Penyewa** → isi nama usaha, paket, masa berlaku, WhatsApp kontak, dan email + password pemiliknya. Kirim data login ke penyewa.
3. Penyewa login → **Perangkat → Tambah nomor — Scan QR** → mulai dipakai.
4. **Pembayaran**: buka penyewa → *Catat pembayaran & perpanjang* (paket, tambah hari, nominal). Masa sewa diperpanjang dari tanggal berakhir saat ini.
5. **Pengingat otomatis**: H-7, H-3, H-1, dan hari H, pesan WhatsApp dikirim ke nomor kontak penyewa dari nomor utama Anda, plus notifikasi di panel Anda. Teks pengingat bisa diubah di `config/license.php` (`reminder_text`).
6. **Telat bayar**: masa sewa lewat → masa tenggang 3 hari → otomatis turun ke paket Gratis (data tetap ada). Untuk menghentikan total: **Tangguhkan** (tidak bisa login, API & pengiriman berhenti).
7. **Bantuan**: tombol **Masuk ke akun ini** untuk melihat aplikasi persis seperti penyewa. Banner kuning menandakan Anda sedang di akun penyewa; klik *Kembali ke panel penyewa* untuk keluar.

## Pendaftaran mandiri (opsional)

Agar calon penyewa bisa mencoba sendiri (paket Gratis) di `https://domain-anda/daftar`, isi di `.env`:

```
SIGNUP_ENABLED=true
LICENSE_CONTACT="Upgrade ke Pro: WhatsApp 08xx-xxxx-xxxx"
```
lalu `php artisan config:clear`. Setiap pendaftar baru muncul di notifikasi Anda; upgrade ke Pro dengan mencatat pembayarannya.

## Kode lisensi (opsional)

Untuk menjual salinan aplikasi yang dipasang di server pembeli sendiri, gunakan kode lisensi bertanda tangan — lihat `docs/LISENSI.md`. Untuk penyewa di server Anda, cukup atur paket lewat panel Penyewa (tidak perlu kode).
