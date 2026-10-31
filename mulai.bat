@echo off
REM ============================================================
REM  ALMARA Gateway WA - jalankan layanan pendukung (Windows/Laragon)
REM  Klik dua kali file ini setelah Laragon (Apache + MySQL) menyala.
REM  Membuka 2 jendela yang HARUS tetap terbuka:
REM    1. Scheduler  : broadcast terjadwal, eskalasi tiket
REM    2. Engine WA  : koneksi WhatsApp Scan QR
REM  Untuk berhenti: tutup kedua jendela tersebut.
REM  @2026-Alifiandra
REM ============================================================
cd /d "%~dp0"

where php >nul 2>nul || (echo PHP tidak ditemukan. Jalankan dari Laragon: Menu ^> Tools ^> Path ^> Add Laragon to Path. & pause & exit /b 1)
where npm >nul 2>nul || (echo Node.js belum terpasang. Unduh di https://nodejs.org lalu ulangi. & pause & exit /b 1)

start "ALMARA - Scheduler (jangan ditutup)" cmd /k "php artisan schedule:work"
start "ALMARA - Engine WhatsApp (jangan ditutup)" cmd /k "cd /d engine & (if not exist node_modules call npm install) & npm start"

timeout /t 5 /nobreak >nul
start "" "http://wagateway-almara.test"
echo Layanan dijalankan. Jendela ini boleh ditutup.
timeout /t 3 >nul
