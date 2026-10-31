<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\CampaignService;
use App\Support\SendingQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** v0.8: jeda acak + mengetik (engine), batas harian & pemanasan nomor. @2026-Alifiandra */
class AntiBlockTest extends TestCase
{
    use RefreshDatabase;

    private function qrDevice(int $ageDays): Device
    {
        config(['whatsapp.engine.url' => 'http://engine.test', 'whatsapp.engine.token' => 'tok']);

        return Device::create(['name' => 'WA', 'provider' => 'qr', 'is_active' => true, 'is_default' => true, 'status' => 'connected',
            'credentials' => ['webhook_token' => 'wh'], 'paired_at' => now()->subDays($ageDays - 1)]);
    }

    private function campaign(Device $device, int $contacts, int $rate = 120): Campaign
    {
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Halo {{name}}']);
        foreach (range(1, $contacts) as $i) {
            Contact::create(['phone' => '62813'.str_pad((string) $i, 8, '0', STR_PAD_LEFT), 'name' => "K{$i}", 'consent' => 'opted_in']);
        }

        $c = Campaign::create(['name' => 'C', 'device_id' => $device->id, 'template_id' => $tpl->id, 'audience' => [], 'rate_per_minute' => $rate,
            'created_by' => User::factory()->create()->id]);
        app(CampaignService::class)->start($c);

        return $c->refresh();
    }

    public function test_warmup_and_daily_limit(): void
    {
        $this->assertSame(30, SendingQuota::dailyLimit($this->qrDevice(1)));
        $this->assertSame(60, SendingQuota::dailyLimit($this->qrDevice(5)));
        $this->assertSame(120, SendingQuota::dailyLimit($this->qrDevice(10)));
        $this->assertSame(200, SendingQuota::dailyLimit($this->qrDevice(20)));
        $this->assertSame(300, SendingQuota::dailyLimit($this->qrDevice(40)));

        Setting::set('warmup_enabled', '0');
        $this->assertSame(300, SendingQuota::dailyLimit($this->qrDevice(1)));
        Setting::set('broadcast_daily_limit', '0');
        $this->assertNull(SendingQuota::dailyLimit($this->qrDevice(1)));

        $log = Device::create(['name' => 'Sim', 'provider' => 'log', 'is_active' => true]);
        $this->assertNull(SendingQuota::dailyLimit($log)); // hanya untuk Scan QR
    }

    public function test_broadcast_sends_humanized_and_stops_at_daily_limit(): void
    {
        $payloads = [];
        Http::fake(function (Request $r) use (&$payloads) {
            $payloads[] = $r->data();

            return Http::response(['success' => true, 'id' => 'Q'.count($payloads), 'queued' => true]);
        });

        Setting::set('broadcast_delay_min', '2');
        Setting::set('broadcast_delay_max', '4');   // rata-rata 3 + 3 (mengetik) -> 10 / menit
        Setting::set('broadcast_daily_limit', '7');
        Setting::set('warmup_enabled', '0');

        $device = $this->qrDevice(40);
        $campaign = $this->campaign($device, 12);
        $service = app(CampaignService::class);

        $this->assertSame(7, $service->runBatch($campaign)); // dibatasi kuota harian (7 < 10/menit)
        $this->assertSame(['min_delay' => 2, 'max_delay' => 4, 'typing' => true], $payloads[0]['humanize']);

        $this->travel(2)->minutes();
        $this->assertSame(0, $service->runBatch($campaign->refresh())); // kuota habis -> menunggu besok
        $this->assertSame('running', $campaign->refresh()->status);

        $admin = User::factory()->create(['role' => 'owner']);
        $this->actingAs($admin)->get("/broadcast/{$campaign->id}")->assertOk()->assertSee('Batas broadcast harian');
        $this->actingAs($admin)->get('/perangkat')->assertOk()->assertSee('7/7');

        $this->travel(1)->days();
        $this->assertSame(5, $service->runBatch($campaign->refresh())); // sisa dilanjutkan besok
    }

    public function test_per_minute_cap_from_delay_and_engine_failure_status(): void
    {
        Http::fake(fn () => Http::response(['success' => true, 'id' => 'X'.uniqid(), 'queued' => true]));
        Setting::set('broadcast_delay_min', '10');
        Setting::set('broadcast_delay_max', '20'); // 15 + 3 -> 3 / menit
        Setting::set('warmup_enabled', '0');

        $device = $this->qrDevice(40);
        $campaign = $this->campaign($device, 10);

        $this->assertSame(3, SendingQuota::maxPerMinute());
        $this->assertSame(3, app(CampaignService::class)->runBatch($campaign));

        // engine melaporkan gagal setelah antrean
        $msg = Message::where('campaign_id', $campaign->id)->first();
        $this->withHeaders(['X-Webhook-Token' => 'wh'])->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'status', 'id' => $msg->provider_message_id, 'status' => 'failed', 'error' => 'Sesi WhatsApp terputus'])->assertOk();
        $this->assertSame('failed', $msg->refresh()->status);
        $this->assertStringContainsString('terputus', $msg->error);
        $this->assertDatabaseHas('campaign_recipients', ['message_id' => $msg->id, 'status' => 'failed']);
    }

    public function test_inbox_reply_is_not_delayed(): void
    {
        $payloads = [];
        Http::fake(function (Request $r) use (&$payloads) {
            $payloads[] = $r->data();

            return Http::response(['success' => true, 'id' => 'R1']);
        });
        $device = $this->qrDevice(40);
        app(\App\Services\MessageService::class)->queueText($device, '081300000001', 'Halo', User::factory()->create());

        $this->assertArrayNotHasKey('humanize', $payloads[0]);
    }
}
