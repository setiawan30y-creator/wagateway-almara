<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use App\Models\Webhook;
use App\Services\CampaignService;
use App\Services\InboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Fase MVP: inbox, CRM, broadcast + proteksi, webhook, role, API. @2026-Alifiandra */
class MvpModulesTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'owner']);
        $this->device = Device::create(['name' => 'Simulasi', 'provider' => 'log', 'credentials' => [], 'is_default' => true, 'is_active' => true]);
        Setting::set('quiet_hours_enabled', '0');
    }

    public function test_all_admin_pages_render(): void
    {
        $contact = Contact::create(['name' => 'Pelanggan Uji', 'phone' => '6281234567890', 'consent' => 'opted_in']);
        $conv = Conversation::forContact($contact, $this->device);
        $tpl = Template::create(['name' => 'Promo', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Halo {{first_name}}']);
        $campaign = Campaign::create(['name' => 'C1', 'device_id' => $this->device->id, 'template_id' => $tpl->id, 'audience' => []]);
        $hook = Webhook::create(['name' => 'H', 'url' => 'https://example.com/h', 'secret' => 's', 'events' => ['message.received']]);

        $urls = ['/dashboard', '/inbox', "/inbox/{$conv->id}", '/kontak', '/kontak?view=pipeline', '/kontak/tambah', "/kontak/{$contact->id}",
            "/kontak/{$contact->id}/ubah", '/template', '/template/tambah', "/template/{$tpl->id}/ubah", '/broadcast', '/broadcast/buat',
            "/broadcast/{$campaign->id}", '/webhook', '/webhook/tambah', "/webhook/{$hook->id}/ubah", '/pengguna', '/pengguna/tambah',
            '/log', '/log?tab=api', '/log?tab=webhook', '/pengaturan', '/notifikasi', '/kirim', '/pesan', '/perangkat', '/api-key', '/akun'];

        foreach ($urls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_agent_cannot_access_admin_pages(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($agent)->get('/inbox')->assertOk();
        $this->actingAs($agent)->get('/kontak')->assertOk();
        foreach (['/broadcast', '/template', '/perangkat', '/api-key', '/webhook', '/pengguna', '/pengaturan', '/log'] as $url) {
            $this->actingAs($agent)->get($url)->assertForbidden();
        }
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_inbound_creates_conversation_and_stop_opts_out(): void
    {
        $inbound = app(InboundService::class);
        $inbound->receive($this->device, '081211112222', 'Halo, mau tanya', 'wamid.1', 'Budi Contoh');

        $contact = Contact::where('phone', '6281211112222')->first();
        $this->assertSame('Budi Contoh', $contact->name);
        $this->assertSame(1, $contact->conversation->unread_count);
        $this->assertDatabaseHas('app_notifications', ['type' => 'message.received', 'user_id' => $this->admin->id]);

        // duplikat diabaikan
        $this->assertNull($inbound->receive($this->device, '081211112222', 'Halo, mau tanya', 'wamid.1'));

        $inbound->receive($this->device, '081211112222', 'STOP', 'wamid.2');
        $this->assertSame('opted_out', $contact->refresh()->consent);
        $this->assertDatabaseHas('consent_events', ['contact_id' => $contact->id, 'consent' => 'opted_out', 'source' => 'keyword']);
        $this->assertSame(1, Message::where('contact_id', $contact->id)->where('direction', 'out')->count()); // balasan otomatis
    }

    public function test_inbox_reply_note_assign_and_simulate(): void
    {
        $this->actingAs($this->admin)->post('/inbox/simulasi', ['from' => '081233334444', 'name' => 'Sinta', 'message' => 'Pesanan saya?'])->assertRedirect();
        $conv = Conversation::first();

        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/balas", ['body' => 'Sedang kami cek.'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['conversation_id' => $conv->id, 'direction' => 'out', 'status' => 'sent', 'user_id' => $this->admin->id]);
        $this->assertSame($this->admin->id, $conv->refresh()->assigned_to);

        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/catatan", ['body' => 'Customer prioritas'])->assertRedirect();
        $this->actingAs($this->admin)->patch("/inbox/{$conv->id}", ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $conv->refresh()->status);
        $this->assertSame(1, $conv->notes()->count());
    }

    public function test_campaign_respects_consent_frequency_cap_and_ab_variants(): void
    {
        $tag = Tag::create(['name' => 'promo']);
        $contacts = collect(range(1, 6))->map(fn ($i) => Contact::create(['name' => "Kontak {$i}", 'phone' => '62812000000'.$i, 'consent' => $i <= 4 ? 'opted_in' : ($i === 5 ? 'opted_out' : 'unknown')]));
        $contacts->each(fn ($c) => $c->tags()->attach($tag));

        $tpl = Template::create(['name' => 'Promo', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Halo {{first_name}} A']);
        $tpl->variants()->create(['label' => 'B', 'body' => 'Halo {{name}} B']);

        // kontak 4 baru saja menerima broadcast lain -> frequency cap
        $old = Campaign::create(['name' => 'lama', 'device_id' => $this->device->id, 'template_id' => $tpl->id, 'audience' => []]);
        Message::create(['contact_id' => $contacts[3]->id, 'campaign_id' => $old->id, 'device_id' => $this->device->id, 'direction' => 'out', 'to' => $contacts[3]->phone, 'body' => 'x', 'status' => 'sent']);

        $campaign = Campaign::create(['name' => 'Promo', 'device_id' => $this->device->id, 'template_id' => $tpl->id, 'audience' => ['tags' => [$tag->id]], 'rate_per_minute' => 60, 'created_by' => $this->admin->id]);
        $service = app(CampaignService::class);
        $service->start($campaign);
        $service->runBatch($campaign->refresh());

        $campaign->refresh();
        $this->assertSame('completed', $campaign->status);
        $stats = $campaign->stats();
        $this->assertSame(3, $stats['sent']);
        $this->assertSame(3, $stats['skipped']); // opt-out, unknown, frequency cap
        $this->assertSame(2, $campaign->recipients()->whereNotNull('template_variant_id')->distinct()->count('template_variant_id'));
        $this->assertTrue(Message::where('campaign_id', $campaign->id)->where('body', 'Halo Kontak 1 B')->exists() || Message::where('campaign_id', $campaign->id)->where('body', 'Halo Kontak A')->exists());
    }

    public function test_campaign_waits_during_quiet_hours_and_respects_rate(): void
    {
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Info']);
        foreach (range(1, 5) as $i) {
            Contact::create(['phone' => '62813000000'.$i, 'consent' => 'opted_in']);
        }

        $campaign = Campaign::create(['name' => 'Rate', 'device_id' => $this->device->id, 'template_id' => $tpl->id, 'audience' => [], 'rate_per_minute' => 2]);
        $service = app(CampaignService::class);
        $service->start($campaign);

        Setting::set('quiet_hours_enabled', '1');
        Setting::set('quiet_start', '00:00');
        Setting::set('quiet_end', '23:59');
        $this->assertSame(0, $service->runBatch($campaign->refresh()));

        Setting::set('quiet_hours_enabled', '0');
        $this->assertSame(2, $service->runBatch($campaign->refresh()));
        $this->assertSame(0, $service->runBatch($campaign->refresh())); // kuota menit ini habis
        $this->assertSame('running', $campaign->refresh()->status);
    }

    public function test_campaign_auto_pauses_on_high_failure(): void
    {
        Setting::set('auto_pause_min_sample', '5');
        $bad = Device::create(['name' => 'Meta', 'provider' => 'meta', 'credentials' => [], 'is_active' => true]); // kredensial kosong -> gagal
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Info']);
        foreach (range(1, 8) as $i) {
            Contact::create(['phone' => '62814000000'.$i, 'consent' => 'opted_in']);
        }

        $campaign = Campaign::create(['name' => 'Gagal', 'device_id' => $bad->id, 'template_id' => $tpl->id, 'audience' => [], 'rate_per_minute' => 100]);
        $service = app(CampaignService::class);
        $service->start($campaign);
        $service->runBatch($campaign->refresh());

        $this->assertSame('paused', $campaign->refresh()->status);
        $this->assertStringContainsString('gagal', $campaign->pause_reason);
    }

    public function test_campaign_created_from_panel_and_preview(): void
    {
        Contact::create(['phone' => '6281500000001', 'consent' => 'opted_in']);
        Contact::create(['phone' => '6281500000002', 'consent' => 'opted_out']);
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Info']);

        $this->actingAs($this->admin)->postJson('/broadcast/pratinjau', [])->assertJson(['total' => 2, 'eligible' => 1, 'opted_out' => 1]);

        $this->actingAs($this->admin)->post('/broadcast', [
            'name' => 'Panel', 'device_id' => $this->device->id, 'template_id' => $tpl->id, 'rate_per_minute' => 20, 'mode' => 'now',
        ])->assertRedirect();

        $this->assertSame('completed', Campaign::where('name', 'Panel')->first()->status);
        $this->artisan('campaigns:run')->assertSuccessful();
    }

    public function test_outgoing_webhook_is_signed(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);
        $hook = Webhook::create(['name' => 'H', 'url' => 'https://example.com/hook', 'secret' => 'rahasia', 'events' => ['message.received'], 'is_active' => true]);

        app(InboundService::class)->receive($this->device, '081266667777', 'Halo');
        app()->terminate(); // jalankan job afterResponse

        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Almara-Timestamp')[0].'.'.$request->body(), 'rahasia');

            return $request->url() === 'https://example.com/hook'
                && $request->header('X-Almara-Event')[0] === 'message.received'
                && $request->header('X-Almara-Signature')[0] === $expected;
        });
        $this->assertNotNull($hook->deliveries()->first()->delivered_at);

        $this->actingAs($this->admin)->post("/webhook/{$hook->id}/tes")->assertRedirect()->assertSessionHas('success');
    }

    public function test_contact_import_and_bulk_actions(): void
    {
        $csv = "name;phone;email;tags\nAndi Contoh;0812 1111 0001;andi@contoh.com;\"vip,promo\"\nTidak Valid;123;;\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('kontak.csv', $csv);

        $this->actingAs($this->admin)->post('/kontak/import', ['file' => $file, 'consent' => '1'])->assertRedirect()->assertSessionHas('success');
        $c = Contact::where('phone', '6281211110001')->first();
        $this->assertSame('opted_in', $c->consent);
        $this->assertEqualsCanonicalizing(['vip', 'promo'], $c->tags->pluck('name')->all());

        $this->actingAs($this->admin)->post('/kontak/massal', ['ids' => [$c->id], 'action' => 'opt_out'])->assertRedirect();
        $this->assertSame('opted_out', $c->refresh()->consent);
    }

    public function test_qr_and_meta_inbound_webhooks(): void
    {
        $qr = Device::create(['name' => 'QR', 'provider' => 'qr', 'credentials' => ['base_url' => 'http://127.0.0.1:3000', 'webhook_token' => 'tok123'], 'is_active' => true]);

        $this->postJson("/api/webhooks/qr/{$qr->id}", ['from' => '6281277778888', 'message' => 'Halo QR'])->assertStatus(401);
        $this->withHeaders(['X-Webhook-Token' => 'tok123'])->postJson("/api/webhooks/qr/{$qr->id}", ['from' => '6281277778888', 'message' => 'Halo QR', 'name' => 'Rina'])
            ->assertOk()->assertJsonPath('received', true);
        $this->assertDatabaseHas('messages', ['direction' => 'in', 'body' => 'Halo QR', 'device_id' => $qr->id]);

        $meta = Device::create(['name' => 'Meta', 'provider' => 'meta', 'credentials' => ['phone_number_id' => 'PNID', 'access_token' => 'x'], 'is_active' => true]);
        $this->postJson('/api/webhooks/meta', ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => 'PNID'],
            'contacts' => [['wa_id' => '6281299990000', 'profile' => ['name' => 'Dewi']]],
            'messages' => [['from' => '6281299990000', 'id' => 'wamid.X', 'type' => 'text', 'text' => ['body' => 'Halo Meta']]],
        ]]]]]])->assertOk();
        $this->assertDatabaseHas('messages', ['direction' => 'in', 'body' => 'Halo Meta', 'device_id' => $meta->id]);
        $this->assertSame('Dewi', Contact::where('phone', '6281299990000')->value('name'));
    }

    public function test_api_v1_new_endpoints(): void
    {
        [, $key] = ApiKey::generate($this->admin, 'Test');
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);
        $tpl->variants()->create(['label' => 'A', 'body' => 'Halo {{name}}']);

        $this->withToken($key)->postJson('/api/v1/contacts', ['phone' => '081288880000', 'name' => 'Api User', 'tags' => ['vip'], 'consent' => 'opted_in'])
            ->assertStatus(201)->assertJsonPath('data.phone', '6281288880000')->assertJsonPath('data.consent', 'opted_in');
        $this->withToken($key)->getJson('/api/v1/contacts?tag=vip')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($key)->getJson('/api/v1/templates')->assertOk()->assertJsonPath('data.0.variants.0.label', 'A');

        $this->withToken($key)->postJson('/api/v1/campaigns', ['name' => 'API', 'template_id' => $tpl->id, 'tags' => ['vip'], 'start' => true])
            ->assertStatus(201)->assertJsonPath('data.status', 'running')->assertJsonPath('meta.audience_preview.eligible', 1);
        $this->withToken($key)->getJson('/api/v1/campaigns')->assertOk();

        $this->withToken($key)->postJson('/api/v1/webhooks', ['name' => 'W', 'url' => 'https://example.com/w', 'events' => ['message.status']])
            ->assertStatus(201)->assertJsonStructure(['data' => ['secret']]);
        $this->withToken($key)->getJson('/api/v1/webhooks')->assertOk()->assertJsonMissingPath('data.0.secret');

        $this->withToken($key)->getJson('/api/v1/analytics?days=7')->assertOk()->assertJsonStructure(['data' => ['messages', 'contacts', 'daily']]);
        $this->assertGreaterThan(0, \App\Models\ApiLog::count());
    }

    public function test_template_render_variables(): void
    {
        $c = new Contact(['name' => 'Siti Aminah', 'phone' => '628123']);
        $this->assertSame('Halo Siti, Siti Aminah (628123) {{unknown}}', Template::render('Halo {{first_name}}, {{ name }} ({{phone}}) {{unknown}}', $c));
    }

    public function test_user_management(): void
    {
        $this->actingAs($this->admin)->post('/pengguna', ['name' => 'CS Satu', 'email' => 'cs1@contoh.com', 'role' => 'agent', 'password' => 'rahasia123', 'is_active' => '1'])->assertRedirect('/pengguna');
        $this->post('/logout');
        $this->post('/login', ['email' => 'cs1@contoh.com', 'password' => 'rahasia123'])->assertRedirect('/dashboard');
    }
}
