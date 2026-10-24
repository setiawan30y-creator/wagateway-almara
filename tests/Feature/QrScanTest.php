<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Tambah perangkat via scan QR (engine bawaan). @2026-Alifiandra */
class QrScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_flow_start_qr_connected_and_send(): void
    {
        config(['whatsapp.engine.url' => 'http://engine.test', 'whatsapp.engine.token' => 'tok']);
        $connected = false;

        Http::fake(function (Request $r) use (&$connected) {
            $path = parse_url($r->url(), PHP_URL_PATH);

            return match ($path) {
                '/sessions/start' => Http::response(['success' => true, 'connected' => false, 'message' => 'Menunggu scan QR.']),
                '/qr' => $connected ? Http::response(['connected' => true, 'message' => 'Terhubung']) : Http::response(['connected' => false, 'qr' => 'data:image/png;base64,AAA']),
                '/status' => Http::response(['connected' => $connected, 'phone' => '6281200001111', 'message' => $connected ? 'Terhubung sebagai +6281200001111.' : 'Menunggu scan QR.']),
                '/send-message' => Http::response(['success' => true, 'id' => 'WAID123']),
                '/sessions/logout' => Http::response(['success' => true]),
                default => Http::response([], 404),
            };
        });

        $admin = User::factory()->create(['role' => 'owner']);
        $this->actingAs($admin)->post('/perangkat/scan', ['name' => 'WA CS'])->assertRedirect();
        $device = Device::where('name', 'WA CS')->first();
        $this->assertSame('qr', $device->provider);
        $this->assertNotEmpty($device->credential('webhook_token'));
        $this->assertTrue($device->is_default);

        $this->actingAs($admin)->get("/perangkat/{$device->id}/pairing")->assertOk()->assertDontSee('tidak dapat dihubungi');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sessions/start')
            && $r['webhook_url'] === url('/api/webhooks/qr/'.$device->id)
            && $r['webhook_token'] === $device->credential('webhook_token')
            && $r->hasHeader('Authorization', 'Bearer tok'));

        $this->actingAs($admin)->getJson("/perangkat/{$device->id}/qr")->assertJson(['connected' => false, 'qr' => 'data:image/png;base64,AAA']);

        $connected = true;
        $this->actingAs($admin)->getJson("/perangkat/{$device->id}/qr")->assertJson(['connected' => true, 'phone' => '6281200001111']);
        $this->assertSame('connected', $device->refresh()->status);

        $this->actingAs($admin)->post('/kirim', ['device_id' => $device->id, 'to' => '081299998888', 'message' => 'Halo'])->assertRedirect();
        $msg = Message::where('direction', 'out')->first();
        $this->assertSame('sent', $msg->status);
        $this->assertSame('WAID123', $msg->provider_message_id);

        // status & sesi dari engine
        $h = ['X-Webhook-Token' => $device->credential('webhook_token')];
        $this->withHeaders($h)->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'status', 'id' => 'WAID123', 'status' => 'read'])->assertOk();
        $this->assertSame('read', $msg->refresh()->status);
        $this->withHeaders($h)->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'session', 'status' => 'logged_out'])->assertOk();
        $this->assertSame('disconnected', $device->refresh()->status);
        $this->assertDatabaseHas('app_notifications', ['type' => 'device.disconnected']);

        $this->actingAs($admin)->post("/perangkat/{$device->id}/logout")->assertRedirect()->assertSessionHas('success');
    }

    public function test_pair_page_shows_hint_when_engine_offline(): void
    {
        config(['whatsapp.engine.url' => 'http://127.0.0.1:9']);
        $admin = User::factory()->create();
        $device = Device::create(['name' => 'QR', 'provider' => 'qr', 'credentials' => [], 'is_active' => true]);

        $this->actingAs($admin)->get("/perangkat/{$device->id}/pairing")->assertOk()->assertSee('npm start');
    }
}
