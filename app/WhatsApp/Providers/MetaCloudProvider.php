<?php

namespace App\WhatsApp\Providers;

use App\Models\Device;
use App\WhatsApp\ConnectionStatus;
use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter resmi Meta WhatsApp Cloud API.
 * Catatan: pesan teks bebas hanya bisa dikirim dalam 24 jam customer service window;
 * di luar itu Meta mewajibkan template yang sudah disetujui.
 * @2026-Alifiandra
 */
class MetaCloudProvider implements WhatsAppProvider
{
    public function sendText(Device $device, string $to, string $body): SendResult
    {
        $phoneNumberId = $device->credential('phone_number_id');

        if (! $phoneNumberId || ! $device->credential('access_token')) {
            return SendResult::failure('Kredensial Meta belum lengkap (phone_number_id / access_token).');
        }

        try {
            $response = $this->client($device)->post("{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $body],
            ]);
        } catch (ConnectionException $e) {
            return SendResult::failure('Tidak dapat terhubung ke Meta Graph API.', retryable: true);
        }

        if ($response->successful()) {
            return SendResult::success($response->json('messages.0.id'));
        }

        $error = $response->json('error.message') ?? ('HTTP '.$response->status());

        return SendResult::failure('Meta: '.$error, retryable: $response->serverError() || $response->status() === 429);
    }

    public function status(Device $device): ConnectionStatus
    {
        $phoneNumberId = $device->credential('phone_number_id');

        if (! $phoneNumberId) {
            return new ConnectionStatus(false, 'phone_number_id belum diisi.');
        }

        try {
            $response = $this->client($device)->get($phoneNumberId, [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);
        } catch (ConnectionException $e) {
            return new ConnectionStatus(false, 'Tidak dapat terhubung ke Meta Graph API.');
        }

        if (! $response->successful()) {
            return new ConnectionStatus(false, 'Meta: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        return new ConnectionStatus(true, sprintf(
            'Terhubung: %s (%s), kualitas: %s',
            $response->json('display_phone_number', '-'),
            $response->json('verified_name', '-'),
            $response->json('quality_rating', '-'),
        ));
    }

    public static function credentialFields(): array
    {
        return [
            'phone_number_id' => ['label' => 'Phone Number ID', 'type' => 'text', 'required' => true, 'placeholder' => 'ID nomor dari Meta App Dashboard'],
            'access_token' => ['label' => 'Access Token', 'type' => 'password', 'required' => true, 'placeholder' => 'Permanent / system user token'],
            'business_account_id' => ['label' => 'WhatsApp Business Account ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Opsional'],
        ];
    }

    protected function client(Device $device): PendingRequest
    {
        $base = rtrim(config('whatsapp.meta.graph_url'), '/').'/'.config('whatsapp.meta.graph_version').'/';

        return Http::baseUrl($base)
            ->withToken((string) $device->credential('access_token'))
            ->acceptJson()
            ->timeout(config('whatsapp.http_timeout'));
    }
}
