# Panduan Pemasangan ALMARA Gateway WA

Versi aplikasi: **0.5.0** · Developer: **@2026-Alifiandra**

Panduan ini berisi:

1. [Kebutuhan](#1-kebutuhan)
2. [Pemasangan di komputer Windows (Laragon)](#2-pemasangan-di-windows-laragon)
3. [Login pertama & pengamanan awal](#3-login-pertama--pengamanan-awal)
4. [Menghubungkan nomor WhatsApp](#4-menghubungkan-nomor-whatsapp)
5. [Mengaktifkan AI Agent](#5-mengaktifkan-ai-agent)
6. [Membuka akses dari internet (domain / tunnel)](#6-membuka-akses-dari-internet)
7. [Pemasangan di server produksi (Ubuntu 24.04)](#7-pemasangan-di-server-produksi-ubuntu-2404)
8. [Update ke versi baru](#8-update-ke-versi-baru)
9. [Backup & pemulihan](#9-backup--pemulihan)
10. [Pemecahan masalah](#10-pemecahan-masalah)

---

## 1. Kebutuhan

| Komponen | Versi | Keterangan |
|---|---|---|
| PHP | **8.3 atau lebih baru** | Ekstensi: mbstring, openssl, pdo_mysql, tokenizer, ctype, fileinfo, curl, intl (disarankan) |
| Composer | 2.x | Pengelola paket PHP |
| MySQL / MariaDB | MySQL 8 / MariaDB 10.6+ | Database |
| Node.js | **20 atau lebih baru** | Hanya untuk engine **Scan QR** (folder `engine/`) |
| Git | terbaru | Mengambil & memperbarui kode |
| Browser | Chrome / Edge / Firefox terbaru | Panel admin (bisa diinstal sebagai aplikasi / PWA) |

Laragon versi terbaru umumnya sudah menyertakan PHP, MySQL, Composer, Node.js, dan Git.

Cek di PowerShell:

```powershell
php -v
composer -V
node -v
git --version
```

---

## 2. Pemasangan di Windows (Laragon)

Semua perintah dijalankan di **PowerShell**. Jalankan baris per baris.

### 2.1 Ambil kode

```powershell
cd C:\laragon\www
git clone https://github.com/setiawan30y-creator/wagateway-almara.git
cd C:\laragon\www\wagateway-almara
```

> Folder sudah ada tapi belum terhubung ke Git? Jalankan:
> ```powershell
> cd C:\laragon\www\wagateway-almara
> git init -b main
> git remote add origin https://github.com/setiawan30y-creator/wagateway-almara.git
> git fetch origin
> git reset --mixed origin/main
> git checkout -- .
> ```

### 2.2 Pasang dependensi & konfigurasi

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

### 2.3 Buat token rahasia engine (dipakai Laravel & engine Scan QR)

```powershell
$t = -join ((48..57)+(65..90)+(97..122) | Get-Random -Count 40 | ForEach-Object {[char]$_})
(Get-Content .env) -replace '^WA_ENGINE_TOKEN=.*', "WA_ENGINE_TOKEN=$t" | Set-Content .env
Copy-Item engine\.env.example engine\.env
(Get-Content engine\.env) -replace '^ENGINE_TOKEN=.*', "ENGINE_TOKEN=$t" | Set-Content engine\.env
```

### 2.4 (Opsional) Ubah akun admin awal

Buka `.env` dengan Notepad lalu ubah sebelum langkah 2.5:

```powershell
notepad .env
```

```
ADMIN_NAME="Administrator"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=ubah-password-ini
```

### 2.5 Buat database & tabel

Pastikan MySQL di Laragon sudah **Start**.

```powershell
$mysql = (Get-ChildItem C:\laragon\bin\mysql -Directory | Select-Object -First 1).FullName + "\bin\mysql.exe"
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS wagateway_almara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
```

### 2.6 Pasang engine Scan QR

```powershell
cd C:\laragon\www\wagateway-almara\engine
npm install
cd C:\laragon\www\wagateway-almara
```

### 2.7 Jalankan aplikasi (3 jendela PowerShell)

| Jendela | Perintah | Fungsi |
|---|---|---|
| 1 | `cd C:\laragon\www\wagateway-almara; php artisan serve` | Web panel di http://127.0.0.1:8000 |
| 2 | `cd C:\laragon\www\wagateway-almara; php artisan schedule:work` | Broadcast terjadwal, eskalasi tiket, pembersihan log |
| 3 | `cd C:\laragon\www\wagateway-almara\engine; npm start` | Engine Scan QR (nomor WhatsApp) |

Biarkan ketiga jendela tetap terbuka selama aplikasi dipakai.

> Memakai virtual host Laragon (`http://wagateway-almara.test`)? Klik **Reload** di Laragon, lalu ubah `APP_URL` di `.env` menjadi alamat tersebut dan jalankan `php artisan config:clear`. Jendela 1 tidak diperlukan.

### 2.7a Cara cepat: `mulai.bat`

Setelah instalasi selesai, cukup **klik dua kali `mulai.bat`** di folder aplikasi (setelah Laragon menyala). File ini membuka jendela **Scheduler** dan **Engine WhatsApp** sekaligus (dan memasang paket engine otomatis bila belum ada), lalu membuka aplikasi di browser. Biarkan kedua jendela itu tetap terbuka; tutup keduanya untuk berhenti.

### 2.8 Uji instalasi (opsional)

```powershell
php artisan test
cd engine; npm test; cd ..
```

---

## 3. Login pertama & pengamanan awal

1. Buka **http://127.0.0.1:8000** → login memakai `ADMIN_EMAIL` / `ADMIN_PASSWORD` dari `.env`
   (bawaan: `admin@example.com` / `ubah-password-ini`).
2. Menu **Akun** → ganti email & password.
3. Menu **Pengguna & Role** → tambah akun CS dengan role **Agent (CS)** (hanya bisa Inbox, Kontak, Tiket, Kirim, Riwayat).
4. Menu **Pengaturan** → atur jam tenang, jam kerja, SLA tiket, kata kunci STOP/MULAI.

Lupa password admin? Jalankan (ganti email & password baru):

```powershell
php artisan tinker --execute="App\Models\User::where('email','admin@example.com')->first()?->update(['password'=>'PasswordBaru123']);"
```

---

## 4. Menghubungkan nomor WhatsApp

### 4.1 Scan QR (paling mudah)

1. Pastikan engine berjalan (jendela 3: `npm start`, muncul *ALMARA WA Engine berjalan di http://127.0.0.1:3000*).
2. Panel → **Perangkat** → **Tambah nomor — Scan QR** → isi nama → **Lanjut ke scan QR**.
3. Di HP: **WhatsApp → ⋮ / Pengaturan → Perangkat tertaut → Tautkan perangkat** → scan QR di layar.
4. Setelah terhubung, nomor tampil otomatis dan status **connected**.

5. Beberapa saat setelah terhubung, chat terbaru dari HP ikut masuk ke **Inbox**. Untuk kontak & grup:
   - **Kontak → Sinkron dari HP** (atau tombol **Sinkron kontak** di Perangkat) — menyalin kontak HP ke menu Kontak. Bila hasilnya 0, tunggu beberapa menit (WhatsApp mengirim kontak bertahap) lalu ulangi.
   - **Grup WA → Sinkron grup dari HP** — daftar grup & anggota; chat grup tampil di Inbox tab *Grup*.

Catatan: jalur Scan QR memakai WhatsApp Web (tidak resmi). Kirim hanya ke kontak yang memberi izin agar nomor tidak dibatasi WhatsApp.

### 4.2 Meta Cloud API (resmi)

1. Buat aplikasi di **developers.facebook.com** → tambahkan produk **WhatsApp** → catat **Phone Number ID** dan buat **Access Token** permanen (System User).
2. Panel → **Perangkat → + Manual** → provider **Meta Cloud API** → isi Phone Number ID & Access Token.
3. Isi `.env`:
   ```
   META_WEBHOOK_VERIFY_TOKEN=isi-teks-bebas
   META_APP_SECRET=app-secret-dari-meta
   ```
   lalu `php artisan config:clear`.
4. Di Meta App Dashboard → WhatsApp → Configuration → Webhook:
   - Callback URL: `https://domain-anda/api/webhooks/meta` (wajib HTTPS publik — lihat bagian 6)
   - Verify token: sama dengan `META_WEBHOOK_VERIFY_TOKEN`
   - Subscribe field: **messages**

### 4.3 Simulasi (tanpa nomor)

Perangkat **Simulasi** sudah dibuat otomatis. Pesan tidak benar-benar terkirim, cocok untuk mencoba alur. Gunakan **Inbox → Simulasi pesan masuk**.

---

## 5. Mengaktifkan AI Agent

1. Daftar di **openrouter.ai** → menu **Keys → Create Key** → salin key (`sk-or-…`).
2. Panel → **AI Agent** → tempel API key → centang **Aktifkan AI Agent** → **Simpan**. Model bawaan `openrouter/free` (gratis).
3. Isi **Basis pengetahuan** (satu topik per item): jam buka, alamat, produk & harga, ongkir, cara order, kebijakan retur.
4. Coba di kotak **Uji AI**.

Kuota model gratis OpenRouter ±50 permintaan/hari tanpa saldo, ±1.000/hari setelah isi saldo sekali $10 (dapat berubah). Pertanyaan yang jawabannya selalu sama sebaiknya dibuat di **Auto-Reply** agar hemat kuota.

---

## 6. Membuka akses dari internet

Diperlukan untuk: **Smart Link** yang dibagikan ke publik, **webhook Meta**, akses panel dari HP di luar jaringan, dan instalasi PWA (wajib HTTPS).

### 6.1 Cepat untuk uji coba — Cloudflare Tunnel (gratis)

```powershell
winget install --id Cloudflare.cloudflared
cloudflared tunnel --url http://127.0.0.1:8000
```

Muncul alamat seperti `https://xxxx.trycloudflare.com`. Masukkan ke `.env`:

```
APP_URL=https://xxxx.trycloudflare.com
```

lalu `php artisan config:clear`. Alamat berubah setiap tunnel dijalankan ulang — untuk alamat tetap gunakan domain sendiri (Cloudflare Tunnel dengan domain, atau server produksi di bagian 7).

### 6.2 Permanen

Gunakan VPS + domain (bagian 7). Jangan membuka port engine (3000) ke internet — engine cukup diakses dari Laravel di server yang sama.

---

## 7. Pemasangan di server produksi (Ubuntu 24.04)

Contoh domain: `wa.domain-anda.com`. Jalankan sebagai user dengan akses `sudo`.

### 7.1 Paket sistem

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mysql-server git unzip supervisor certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt -y install nodejs
```

### 7.2 Database

```bash
sudo mysql -e "CREATE DATABASE wagateway_almara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'wagateway'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_DB';"
sudo mysql -e "GRANT ALL ON wagateway_almara.* TO 'wagateway'@'localhost'; FLUSH PRIVILEGES;"
```

### 7.3 Kode & konfigurasi

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/setiawan30y-creator/wagateway-almara.git
sudo chown -R $USER:www-data wagateway-almara && cd wagateway-almara
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
nano .env
```

Ubah di `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wa.domain-anda.com
DB_DATABASE=wagateway_almara
DB_USERNAME=wagateway
DB_PASSWORD=GANTI_PASSWORD_DB
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
ADMIN_EMAIL=email-admin-anda
ADMIN_PASSWORD=password-awal-yang-kuat
WA_ENGINE_TOKEN=token-acak-panjang
```

```bash
php artisan migrate --seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

cd engine && npm install --omit=dev && cp .env.example .env && nano .env   # ENGINE_TOKEN = WA_ENGINE_TOKEN
cd ..
```

### 7.4 Nginx

`/etc/nginx/sites-available/wagateway`:

```nginx
server {
    listen 80;
    server_name wa.domain-anda.com;
    root /var/www/wagateway-almara/public;
    index index.php;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/wagateway /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d wa.domain-anda.com
```

Atur `upload_max_filesize = 20M` dan `post_max_size = 25M` di `/etc/php/8.3/fpm/php.ini`, lalu `sudo systemctl restart php8.3-fpm`.

### 7.5 Worker antrean, scheduler & engine (Supervisor)

`/etc/supervisor/conf.d/wagateway.conf`:

```ini
[program:wagateway-queue]
command=php /var/www/wagateway-almara/artisan queue:work --sleep=1 --tries=3 --timeout=120
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=130
stdout_logfile=/var/www/wagateway-almara/storage/logs/queue.log

[program:wagateway-scheduler]
command=php /var/www/wagateway-almara/artisan schedule:work
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/wagateway-almara/storage/logs/scheduler.log

[program:wagateway-engine]
command=/usr/bin/node src/server.js
directory=/var/www/wagateway-almara/engine
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/www/wagateway-almara/storage/logs/engine.log
```

```bash
sudo chown -R www-data:www-data /var/www/wagateway-almara/engine
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

Firewall: buka hanya 22, 80, 443.

```bash
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

---

## 8. Update ke versi baru

### Windows (Laragon)

```powershell
cd C:\laragon\www\wagateway-almara
git pull
composer install
php artisan migrate
php artisan optimize:clear
cd engine; npm install; cd ..
```

Tutup lalu jalankan ulang jendela `schedule:work` dan `npm start`. Di browser tekan **Ctrl+F5**.

> **Ke versi 1.0 (multi-penyewa):** **backup database dulu** (bagian 9) sebelum `php artisan migrate`. Semua data lama otomatis menjadi milik akun usaha #1 "Usaha Utama" (paket Pro tanpa batas waktu), dan akun owner pertama menjadi super-admin (menu **Platform → Penyewa**). Panduan menyewakan: `docs/PENYEWAAN.md`.

> **Ke versi 0.9 (paket Gratis / Pro):** setelah update, aplikasi berjalan sebagai paket Gratis sampai lisensi diaktifkan. Pemilik aplikasi jalankan sekali `php artisan license:keygen`, lalu `php artisan license:issue "Nama Usaha" --days=0 --devices=0 --activate` (lihat `docs/LISENSI.md`).

> **Dari versi 0.5 ke 0.6:** agar kontak & riwayat chat HP ikut masuk, perangkat Scan QR yang sudah terhubung perlu **Logout lalu Scan QR ulang** sekali (WhatsApp hanya mengirim kontak & riwayat saat perangkat baru ditautkan). Daftar grup bisa langsung diambil lewat **Grup WA → Sinkron grup dari HP** tanpa scan ulang.

### Server (Ubuntu)

```bash
cd /var/www/wagateway-almara
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
cd engine && npm install --omit=dev && cd ..
sudo supervisorctl restart all
php artisan up
```

---

## 9. Backup & pemulihan

Yang perlu dicadangkan:

| Data | Lokasi |
|---|---|
| Database | `wagateway_almara` |
| Berkas media pesan | `storage/app/private/wa-media/` |
| Konfigurasi & kunci enkripsi | `.env` (**APP_KEY wajib disimpan** — tanpa ini kredensial & API key terenkripsi tidak bisa dibaca) |
| Sesi WhatsApp Scan QR | `engine/storage/` dan `engine/.env` |

### Windows (PowerShell)

```powershell
$tgl = Get-Date -Format "yyyyMMdd-HHmm"
$bk = "C:\backup\wagateway-$tgl"; New-Item -ItemType Directory -Force $bk | Out-Null
$dump = (Get-ChildItem C:\laragon\bin\mysql -Directory | Select-Object -First 1).FullName + "\bin\mysqldump.exe"
& $dump -u root wagateway_almara --result-file="$bk\database.sql"
Copy-Item C:\laragon\www\wagateway-almara\.env "$bk\.env"
Copy-Item C:\laragon\www\wagateway-almara\storage\app\private -Recurse "$bk\media" -ErrorAction SilentlyContinue
Copy-Item C:\laragon\www\wagateway-almara\engine\storage -Recurse "$bk\engine-storage" -ErrorAction SilentlyContinue
Compress-Archive -Path "$bk\*" -DestinationPath "$bk.zip"; Remove-Item $bk -Recurse
```

Pemulihan database:

```powershell
$mysql = (Get-ChildItem C:\laragon\bin\mysql -Directory | Select-Object -First 1).FullName + "\bin\mysql.exe"
Get-Content C:\backup\database.sql | & $mysql -u root wagateway_almara
```

### Server (Ubuntu, harian via cron)

```bash
sudo mkdir -p /var/backups/wagateway
( crontab -l 2>/dev/null; echo '30 2 * * * mysqldump wagateway_almara | gzip > /var/backups/wagateway/db-$(date +\%F).sql.gz && find /var/backups/wagateway -mtime +14 -delete' ) | crontab -
```

Simpan salinan backup di lokasi terpisah (Google Drive, storage lain).

---

## 10. Pemecahan masalah

| Gejala | Penyebab & solusi |
|---|---|
| `Command "servecd" is not defined` | Dua perintah tergabung saat paste. Jalankan satu per satu. |
| Halaman error 500 | Lihat `storage/logs/laravel.log`. Jalankan `php artisan optimize:clear`. Pastikan `.env` ada dan `APP_KEY` terisi. |
| `SQLSTATE[HY000] [1049] Unknown database` | Database belum dibuat — ulangi langkah 2.5. |
| `SQLSTATE[HY000] [2002]` | MySQL belum berjalan. Start MySQL di Laragon / `sudo systemctl start mysql`. |
| Login gagal | Jalankan `php artisan db:seed` atau reset password (bagian 3). |
| Halaman Scan QR: *Engine QR tidak dapat dihubungi* | Jendela `npm start` belum berjalan, atau `WA_ENGINE_TOKEN` ≠ `ENGINE_TOKEN`. Samakan lalu `php artisan config:clear`. |
| QR tidak muncul / terus menunggu | Cek pesan error di jendela engine. Pastikan Node.js ≥ 20 dan `npm install` selesai tanpa error. |
| Pesan masuk dari nomor QR tidak muncul di Inbox | Engine mengirim ke URL yang tercatat saat pairing. Bila `APP_URL`/alamat berubah, buka lagi halaman **Scan QR** perangkat tersebut agar URL diperbarui. |
| Broadcast tidak berjalan | Jendela `php artisan schedule:work` belum dijalankan, atau sedang jam tenang (cek Pengaturan). |
| Pesan status `queued` terus | `QUEUE_CONNECTION=database` tetapi worker tidak berjalan. Jalankan `php artisan queue:work` atau ubah ke `sync` untuk lokal. |
| AI tidak membalas | Cek menu **AI Agent → Riwayat pemanggilan**. Penyebab umum: API key salah, kuota gratis habis (error *rate limit*), AI dijeda karena CS baru membalas, atau pesan sudah tertangkap Auto-Reply. |
| Smart Link tidak bisa dibuka orang lain | Aplikasi masih di `127.0.0.1`. Buka akses internet (bagian 6) dan sesuaikan `APP_URL`. |
| Upload lampiran gagal | Berkas > 16 MB atau batas PHP terlalu kecil — naikkan `upload_max_filesize` dan `post_max_size` di `php.ini`. |

---

## Dokumentasi lain

| Dokumen | Isi |
|---|---|
| [`README.md`](../README.md) | Ringkasan fitur per versi & cara pakai |
| [`docs/api/README.md`](api/README.md) | Daftar endpoint REST API |
| [`docs/api/openapi.yaml`](api/openapi.yaml) | Spesifikasi API lengkap (OpenAPI 3 — buka di editor.swagger.io) |
| [`docs/api/authentication.md`](api/authentication.md) | API key |
| [`docs/api/webhooks.md`](api/webhooks.md) | Webhook masuk & keluar |
| [`docs/api/errors.md`](api/errors.md) | Format respons & kode error |
| [`docs/postman/`](postman/) | Koleksi Postman siap impor |
| [`engine/README.md`](../engine/README.md) | Engine Scan QR |

---
© 2026 ALMARA Gateway WA · @2026-Alifiandra
