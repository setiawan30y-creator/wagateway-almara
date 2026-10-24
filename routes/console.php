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

Schedule::command('campaigns:run')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:escalate')->everyFiveMinutes();
Schedule::command('logs:prune')->daily();
