<?php

namespace App\WhatsApp\Providers;

use App\Models\Device;
use App\WhatsApp\ConnectionStatus;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provider simulasi: tidak mengirim ke WhatsApp, hanya mencatat ke log Laravel.
 * Berguna untuk uji coba alur aplikasi dan API tanpa nomor sungguhan.
 * @2026-Alifiandra
 */
class LogProvider implements WhatsAppProvider
{
    public function sendText(Device $device, string $to, string $body): SendResult
    {
        Log::info('[WA-SIMULASI] Pesan dikirim', [
            'device' => $device->name,
            'to' => $to,
            'length' => mb_strlen($body),
        ]);

        return SendResult::success('sim_'.Str::lower(Str::random(16)));
    }

    public function status(Device $device): ConnectionStatus
    {
        return new ConnectionStatus(true, 'Mode simulasi aktif — pesan hanya dicatat ke storage/logs.');
    }

    public static function credentialFields(): array
    {
        return [];
    }
}
