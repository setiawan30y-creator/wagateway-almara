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
        $this->actingAs($admin)->get("/broadcast/{$campaign->id}")->assertOk()->assertSee('Kuota broadcast harian');
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

    public function test_campaign_page_explains_why_it_waits(): void
    {
        Http::fake(fn () => Http::response(['success' => true, 'id' => 'X'.uniqid(), 'queued' => true]));
        Setting::set('warmup_enabled', '0');
        $device = $this->qrDevice(40);
        $campaign = $this->campaign($device, 3);
        $admin = User::factory()->create(['role' => 'owner']);

        // scheduler belum pernah jalan + ada penerima menunggu
        $this->actingAs($admin)->get("/broadcast/{$campaign->id}")->assertOk()
            ->assertSee('Scheduler tidak berjalan')->assertSee('data-live-refresh', false);

        \Illuminate\Support\Facades\Artisan::call('campaigns:run');
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('scheduler.heartbeat'));

        // nomor terputus + jam tenang
        $device->update(['status' => 'disconnected']);
        Setting::set('quiet_start', '00:00');
        Setting::set('quiet_end', '23:59');
        $c2 = $this->campaign2($device);
        $this->actingAs($admin)->get("/broadcast/{$c2->id}")->assertOk()
            ->assertSee('tidak terhubung')->assertSee('Sedang jam tenang')->assertDontSee('Scheduler tidak berjalan');
    }

    private function campaign2(Device $device): Campaign
    {
        Contact::create(['phone' => '6281399990001', 'name' => 'Z', 'consent' => 'opted_in']);
        $c = Campaign::create(['name' => 'C2', 'device_id' => $device->id, 'template_id' => Template::first()->id, 'audience' => [], 'rate_per_minute' => 10]);
        app(CampaignService::class)->start($c);

        return $c->refresh();
    }

    public function test_live_progress_shows_current_queue_and_recent(): void
    {
        $n = 0;
        Http::fake(function () use (&$n) {
            $n++;

            return Http::response(['success' => true, 'id' => 'L'.$n, 'queued' => true]);
        });
        Setting::set('warmup_enabled', '0');
        Setting::set('broadcast_delay_min', '1');
        Setting::set('broadcast_delay_max', '2');
        $device = $this->qrDevice(40);
        $campaign = $this->campaign($device, 4);
        $this->assertSame(4, app(CampaignService::class)->runBatch($campaign));
        $this->assertSame(4, Message::whereNull('wa_sent_at')->count()); // antre di engine

        $h = ['X-Webhook-Token' => 'wh'];
        $ids = Message::orderBy('id')->pluck('provider_message_id', 'to');
        [$first, $second] = array_map('strval', $ids->keys()->all());

        // pesan pertama terkirim, pesan kedua sedang "mengetik"
        $this->withHeaders($h)->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'progress', 'id' => $ids[$first], 'to' => $first, 'state' => 'sending'])->assertOk();
        $this->withHeaders($h)->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'status', 'id' => $ids[$first], 'status' => 'sent'])->assertOk();
        $this->withHeaders($h)->postJson("/api/webhooks/qr/{$device->id}", ['type' => 'progress', 'id' => $ids[$second], 'to' => $second, 'state' => 'typing', 'wait_ms' => 3000, 'queue' => 2])->assertOk();

        $admin = User::factory()->create(['role' => 'owner']);
        $this->actingAs($admin)->get("/broadcast/{$campaign->id}")->assertOk()->assertSee('data-live-progress', false);
        $this->actingAs($admin)->getJson("/broadcast/{$campaign->id}/live")->assertOk()
            ->assertJsonPath('running', true)
            ->assertJsonPath('current.phone', $second)
            ->assertJsonPath('current.state', 'typing')
            ->assertJsonPath('stats.delivered_to_wa', 1)
            ->assertJsonPath('stats.in_engine', 3)
            ->assertJsonCount(2, 'queue')
            ->assertJsonPath('recent.0.phone', $first)
            ->assertJsonPath('recent.0.status', 'sent');
    }
}
