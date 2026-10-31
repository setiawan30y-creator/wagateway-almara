# Lisensi & Paket (Gratis / Pro)

Panduan untuk **pemilik aplikasi** yang menjual / memasang salinan ALMARA Gateway WA di server pembeli. @2026-Alifiandra

> Sejak versi 1.0, penyewa di server Anda sendiri cukup diatur lewat **Platform → Penyewa** (lihat `docs/PENYEWAAN.md`) — tidak perlu kode lisensi. Kode lisensi di bawah ini berguna bila aplikasi dipasang di server milik pembeli.

## Cara kerja

- Tanpa kode lisensi, aplikasi berjalan sebagai paket **Gratis**:
  - 1 nomor WhatsApp (perangkat *Simulasi* tidak dihitung)
  - 100 pesan keluar per hari
  - Broadcast & AI Agent terkunci
  - Setiap pesan keluar diberi watermark: *"Dikirim via ALMARA Gateway WA"*
- Paket **Pro** membuka semua fitur tanpa watermark. Jumlah nomor & masa aktif ditentukan per kode lisensi.
- Kode lisensi **ditandatangani secara digital** dengan kunci privat milik Anda. Aplikasi hanya menyimpan kunci publik untuk memeriksa, sehingga penyewa tidak bisa membuat atau mengubah kode sendiri (misalnya menaikkan jumlah nomor atau memperpanjang masa aktif).
- Lisensi yang kedaluwarsa masih berlaku 3 hari (masa tenggang) lalu otomatis turun ke Gratis.
- Batas & isi tiap paket bisa diubah di `config/license.php`.

## 1. Sekali saja: buat kunci (di komputer Anda)

```powershell
cd C:\laragon\www\wagateway-almara
php artisan license:keygen
```

Hasilnya:
- **Kunci privat** → `C:\Users\<nama>\.almara-license\private.pem` — RAHASIA. Backup ke flashdisk / tempat aman. Jika hilang, Anda tidak bisa menerbitkan kode baru; jika bocor, orang lain bisa membuat kode sendiri.
- **Kunci publik** → `config\license-public.pem` — ikut aplikasi. Commit & push agar ikut terpasang di server penyewa:

```powershell
git add config/license-public.pem
git commit -m "Kunci publik lisensi"
git push
```

## 2. Aktifkan Pro untuk aplikasi Anda sendiri

```powershell
php artisan license:issue "Nama Usaha Anda" --days=0 --devices=0 --activate
```
`--days=0` = selamanya, `--devices=0` = tanpa batas nomor.

## 3. Menerbitkan kode untuk penyewa

```powershell
php artisan license:issue "Nama Penyewa" --plan=pro --days=30 --devices=3
```
Salin kode yang muncul (satu baris panjang) dan kirim ke penyewa. Pembeli membuka menu **Sistem → Lisensi**, menempel kode, lalu klik **Aktifkan**.

**Perpanjangan:** terbitkan kode baru (mis. `--days=30` lagi) dan minta penyewa menempelkannya — kode lama otomatis tergantikan.

## Batasan yang perlu diketahui

- Aplikasi ini *self-hosted* (kode PHP terbuka). Penyewa yang mengerti pemrograman dan memegang kode bisa saja mengubah kode untuk melewati pembatasan. Cara paling aman: **Anda yang memasang & mengelola server penyewa** (penyewa hanya diberi akses login), bukan memberikan kode programnya.
- Jangan pernah membagikan file `private.pem` atau memasukkannya ke Git.
- `php artisan license:keygen --force` membuat kunci baru — semua kode lisensi lama menjadi tidak berlaku.
