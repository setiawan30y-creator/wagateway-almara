<?php

use App\Models\ApiLog;
use App\Services\CampaignService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduler ALMARA Gateway WA — jalankan:  php artisan schedule:work  (lokal)
| atau cron:  * * * * * php /path/artisan schedule:run  (server)
| @2026-Alifiandra
*/

Artisan::command('campaigns:run', function (CampaignService $service) {
    $result = $service->tick();
    $this->info("Campaign dimulai: {$result['started']}, pesan dikirim: {$result['sent']}");
})->purpose('Proses campaign broadcast terjadwal & berjalan (satu batch)');

Artisan::command('logs:prune {--days=30}', function () {
    $deleted = ApiLog::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Log API dihapus: {$deleted}");
})->purpose('Hapus log API lama');

Artisan::command('tickets:escalate', function (\App\Services\TicketService $service) {
    $this->info('Tiket dieskalasi: '.$service->escalateOverdue());
})->purpose('Eskalasi tiket yang melewati SLA');

// --- Lisensi (dijalankan PEMILIK aplikasi, bukan penyewa) ---------------------------
Artisan::command('license:keygen {--force : Timpa kunci yang sudah ada}', function () {
    $privPath = \App\Support\License::privateKeyPath();
    $pubPath = config('license.public_key_path');

    if (is_file($privPath) && ! $this->option('force')) {
        $this->warn('Kunci privat sudah ada: '.$privPath);
        $this->line('Pakai --force untuk membuat ulang (semua kode lisensi lama menjadi TIDAK berlaku).');

        return 1;
    }

    $pair = \App\Support\License::generateKeyPair();
    @mkdir(dirname($privPath), 0700, true);
    file_put_contents($privPath, $pair['private']);
    @chmod($privPath, 0600);
    file_put_contents($pubPath, $pair['public']);

    $this->info('Kunci lisensi dibuat.');
    $this->line('  Kunci privat (RAHASIA, simpan & backup, JANGAN dibagikan): '.$privPath);
    $this->line('  Kunci publik (ikut aplikasi / di-commit):                  '.$pubPath);
})->purpose('Buat pasangan kunci untuk menerbitkan kode lisensi');

Artisan::command('license:issue {name : Nama penyewa} {--plan=pro} {--days=30 : Masa aktif hari, 0 = selamanya} {--devices=5 : Maks nomor WA, 0 = tanpa batas} {--activate : Langsung aktifkan di aplikasi ini}', function () {
    $privPath = \App\Support\License::privateKeyPath();

    if (! is_file($privPath)) {
        $this->error('Kunci privat tidak ditemukan ('.$privPath.'). Jalankan dulu: php artisan license:keygen');

        return 1;
    }

    $plan = (string) $this->option('plan');
    if (! config('license.plans.'.$plan) || $plan === 'free') {
        $this->error('Paket tidak dikenal: '.$plan.' (pilihan: '.implode(', ', array_diff(array_keys(config('license.plans')), ['free'])).')');

        return 1;
    }

    $days = (int) $this->option('days');
    $devices = (int) $this->option('devices');
    $payload = [
        'id' => 'LIC-'.strtoupper(\Illuminate\Support\Str::random(8)),
        'name' => (string) $this->argument('name'),
        'plan' => $plan,
        'devices' => $devices > 0 ? $devices : null,
        'exp' => $days > 0 ? now()->addDays($days)->toDateString() : null,
        'iat' => now()->toDateString(),
    ];

    $key = \App\Support\License::issue($payload, (string) file_get_contents($privPath));

    $this->info('Kode lisensi '.$payload['id'].' untuk '.$payload['name'].' — paket '.$plan.', '.($payload['devices'] ?? 'tanpa batas').' nomor, berlaku '.($payload['exp'] ? 's.d. '.$payload['exp'] : 'selamanya').':');
    $this->newLine();
    $this->line($key);
    $this->newLine();
    $this->line('Berikan kode di atas ke penyewa: menu Lisensi -> tempel -> Aktifkan.');

    if ($this->option('activate')) {
        \App\Models\Setting::set('license_key', $key);
        \App\Support\License::flush();
        $this->info('Lisensi diaktifkan di aplikasi ini.');
    }
})->purpose('Terbitkan kode lisensi untuk penyewa');

Schedule::command('campaigns:run')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:escalate')->everyFiveMinutes();
Schedule::command('logs:prune')->daily();
