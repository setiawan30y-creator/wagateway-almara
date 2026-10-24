<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\SmartLink;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\InboundService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fase 2: media, ticketing, smart link, auto-reply. @2026-Alifiandra */
class Phase2Test extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'owner']);
        $this->device = Device::create(['name' => 'Simulasi', 'provider' => 'log', 'phone_number' => '6281200000000', 'credentials' => [], 'is_default' => true, 'is_active' => true]);
        Setting::set('quiet_hours_enabled', '0');
    }

    public function test_send_media_from_panel_inbox_and_api(): void
    {
        $img = UploadedFile::fake()->image('foto.jpg', 40, 40);
        $this->actingAs($this->admin)->post('/kirim', ['device_id' => $this->device->id, 'to' => '081211110000', 'message' => 'Lihat ini', 'file' => $img])->assertRedirect('/pesan');

        $m = Message::first();
        $this->assertSame('image', $m->media_type);
        $this->assertSame('sent', $m->status);
        Storage::disk('local')->assertExists($m->media_path);
        $this->actingAs($this->admin)->get('/media/'.$m->public_id)->assertOk();
        $this->get('/pesan')->assertOk()->assertSee('[gambar]');

        $conv = $m->conversation;
        $pdf = UploadedFile::fake()->create('katalog.pdf', 30, 'application/pdf');
        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/balas", ['file' => $pdf])->assertRedirect();
        $this->assertDatabaseHas('messages', ['media_type' => 'document', 'media_name' => 'katalog.pdf']);
        $this->actingAs($this->admin)->get("/inbox/{$conv->id}")->assertOk()->assertSee('katalog.pdf');

        [, $key] = ApiKey::generate($this->admin, 'T');
        $this->withToken($key)->post('/api/v1/messages/media', ['to' => '081211110000', 'caption' => 'Struk', 'file' => UploadedFile::fake()->image('struk.png')], ['Accept' => 'application/json'])
            ->assertStatus(202)->assertJsonPath('data.media.type', 'image')->assertJsonPath('data.body', 'Struk');

        // tanpa login tidak boleh melihat media
        auth()->logout();
        $this->get('/media/'.$m->public_id)->assertRedirect('/login');
    }

    public function test_qr_engine_receives_and_sends_media(): void
    {
        config(['whatsapp.engine.url' => 'http://engine.test']);
        Http::fake(['engine.test/send-media' => Http::response(['success' => true, 'id' => 'MED1'])]);
        $qr = Device::create(['name' => 'QR', 'provider' => 'qr', 'credentials' => ['webhook_token' => 'wt'], 'status' => 'connected', 'is_active' => true]);

        $this->withHeaders(['X-Webhook-Token' => 'wt'])->postJson("/api/webhooks/qr/{$qr->id}", [
            'type' => 'message', 'from' => '6281277770000', 'message' => '', 'id' => 'IN1',
            'media' => ['type' => 'image', 'mime' => 'image/jpeg', 'name' => null, 'data' => base64_encode('fakejpeg')],
        ])->assertOk();
        $in = Message::where('direction', 'in')->first();
        $this->assertSame('image', $in->media_type);
        $this->assertSame('[gambar]', $in->body);

        $this->actingAs($this->admin)->post("/inbox/{$in->conversation_id}/balas", ['device_id' => $qr->id, 'body' => 'Terima kasih', 'file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/send-media') && $r['type'] === 'document' && $r['caption'] === 'Terima kasih' && base64_decode($r['data']) !== false);
        $this->assertSame('MED1', Message::where('direction', 'out')->latest('id')->value('provider_message_id'));
    }

    public function test_ticket_lifecycle_sla_and_escalation(): void
    {
        $contact = Contact::create(['name' => 'Budi', 'phone' => '6281233330000', 'consent' => 'opted_in']);
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($agent)->post('/tiket', [
            'contact_id' => $contact->id, 'subject' => 'Pesanan belum sampai', 'category' => 'order', 'priority' => 'high', 'notify_customer' => '1',
        ])->assertRedirect();

        $t = Ticket::first();
        $this->assertSame('TCK-000001', $t->number);
        $this->assertSame($agent->id, $t->assigned_to);
        $this->assertEqualsWithDelta(now()->addHours(8)->timestamp, $t->sla_due_at->timestamp, 5);
        $this->assertTrue(Message::where('contact_id', $contact->id)->where('body', 'like', '%TCK-000001%')->exists());

        $this->actingAs($agent)->get('/tiket')->assertOk()->assertSee('TCK-000001');
        $this->actingAs($agent)->get("/tiket/{$t->id}")->assertOk();
        $this->actingAs($agent)->post("/tiket/{$t->id}/catatan", ['body' => 'Sudah cek ke kurir'])->assertRedirect();

        $t->update(['sla_due_at' => now()->subHour()]);
        $this->assertSame(1, app(TicketService::class)->escalateOverdue());
        $this->assertSame('urgent', $t->refresh()->priority);
        $this->assertNotNull($t->escalated_at);
        $this->assertSame(0, app(TicketService::class)->escalateOverdue()); // tidak dobel

        $this->actingAs($agent)->patch("/tiket/{$t->id}", ['status' => 'resolved', 'notify_customer' => '1'])->assertRedirect();
        $this->assertSame('resolved', $t->refresh()->status);
        $this->assertNotNull($t->resolved_at);
        $this->assertTrue(Message::where('contact_id', $contact->id)->where('body', 'like', '%telah diselesaikan%')->exists());
        $this->assertGreaterThanOrEqual(5, $t->events()->count());

        $this->artisan('tickets:escalate')->assertSuccessful();

        [, $key] = ApiKey::generate($this->admin, 'T');
        $this->withToken($key)->postJson('/api/v1/tickets', ['phone' => '081244440000', 'subject' => 'Tagihan ganda', 'priority' => 'urgent'])
            ->assertStatus(201)->assertJsonPath('data.priority', 'urgent');
        $this->withToken($key)->getJson('/api/v1/tickets')->assertOk()->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_smart_link_click_redirect_and_attribution(): void
    {
        $tag = Tag::create(['name' => 'lead-ig']);
        $this->actingAs($this->admin)->post('/smart-link', ['name' => 'Promo IG', 'message' => 'Halo, saya mau promo', 'tag_id' => $tag->id, 'is_active' => '1'])->assertRedirect();
        $link = SmartLink::first();
        $this->assertSame('promo-ig', $link->slug);

        auth()->logout();
        $res = $this->get('/l/promo-ig', ['User-Agent' => 'Mozilla/5.0 (iPhone)']);
        $res->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/6281200000000?text=', $res->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('[#promo-ig]'), $res->headers->get('Location'));
        $this->assertSame(1, $link->refresh()->clicks_count);
        $this->assertDatabaseHas('smart_link_clicks', ['smart_link_id' => $link->id, 'device' => 'mobile']);

        app(InboundService::class)->receive($this->device, '081255550000', 'Halo, saya mau promo [#promo-ig]', null, 'Rina');
        $c = Contact::where('phone', '6281255550000')->first();
        $this->assertSame($link->id, $c->smart_link_id);
        $this->assertTrue($c->tags->contains($tag));
        $this->assertSame(1, $link->refresh()->chats_count);
        $this->assertSame('Halo, saya mau promo', Message::where('contact_id', $c->id)->where('direction', 'in')->value('body'));

        $c->update(['stage' => 'customer']);
        $this->assertSame(1, $link->conversions());

        $link->update(['expires_at' => now()->subDay()]);
        $this->get('/l/promo-ig')->assertNotFound();

        $this->actingAs($this->admin)->get("/smart-link/{$link->id}")->assertOk()->assertSee('/l/promo-ig');
        $this->actingAs($this->admin)->get('/smart-link')->assertOk();
    }

    public function test_auto_reply_keyword_welcome_away_and_cooldown(): void
    {
        $tag = Tag::create(['name' => 'tanya-harga']);
        AutoReply::create(['name' => 'Harga', 'trigger' => 'keyword', 'match_type' => 'contains', 'keywords' => 'harga, price list', 'reply' => 'Halo {{first_name}}, ini daftar harga.', 'add_tag_id' => $tag->id, 'cooldown_minutes' => 60, 'priority' => 10, 'is_active' => true]);
        AutoReply::create(['name' => 'Sambutan', 'trigger' => 'welcome', 'match_type' => 'contains', 'reply' => 'Selamat datang!', 'cooldown_minutes' => 0, 'priority' => 100, 'is_active' => true]);
        AutoReply::create(['name' => 'Tutup', 'trigger' => 'away', 'match_type' => 'contains', 'reply' => 'Kami sedang tutup.', 'cooldown_minutes' => 0, 'priority' => 100, 'is_active' => true]);
        Setting::set('work_days', '1,2,3,4,5,6,7');
        Setting::set('work_start', '00:00');
        Setting::set('work_end', '23:59');

        $in = app(InboundService::class);
        $in->receive($this->device, '081266660000', 'Selamat pagi', null, 'Sari Dewi');
        $c = Contact::where('phone', '6281266660000')->first();
        $this->assertSame(['Selamat datang!'], Message::where('contact_id', $c->id)->where('direction', 'out')->pluck('body')->all());

        $in->receive($this->device, '081266660000', 'Berapa harga paket?');
        $this->assertTrue(Message::where('contact_id', $c->id)->where('body', 'Halo Sari, ini daftar harga.')->exists());
        $this->assertTrue($c->tags()->where('tags.id', $tag->id)->exists());

        $in->receive($this->device, '081266660000', 'harga lagi');
        $this->assertSame(1, Message::where('contact_id', $c->id)->where('body', 'like', '%daftar harga%')->count()); // cooldown

        Setting::set('work_start', '00:00');
        Setting::set('work_end', '00:01');
        $in->receive($this->device, '081266660000', 'halo masih buka?');
        $this->assertTrue(Message::where('contact_id', $c->id)->where('body', 'Kami sedang tutup.')->exists());

        $c->setConsent('opted_out', 'manual');
        $before = Message::where('direction', 'out')->count();
        $in->receive($this->device, '081266660000', 'harga');
        $this->assertSame($before, Message::where('direction', 'out')->count());

        $this->actingAs($this->admin)->get('/auto-reply')->assertOk()->assertSee('Harga');
        $this->actingAs($this->admin)->postJson('/auto-reply/uji', ['text' => 'minta price list dong'])->assertJson(['matched' => true, 'rule' => 'Harga']);
        $this->actingAs($this->admin)->get('/auto-reply/buat')->assertOk();
    }

    public function test_new_pages_render_for_admin_and_agent(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        foreach (['/tiket', '/tiket/buat'] as $u) {
            $this->actingAs($agent)->get($u)->assertOk();
        }
        foreach (['/smart-link', '/auto-reply'] as $u) {
            $this->actingAs($agent)->get($u)->assertForbidden();
            $this->actingAs($this->admin)->get($u)->assertOk();
        }
        $this->actingAs($this->admin)->get('/smart-link/buat')->assertOk();
        $this->actingAs($this->admin)->get('/pengaturan')->assertOk()->assertSee('SLA tiket');
        $this->actingAs($this->admin)->put('/pengaturan', array_merge(
            collect(Setting::DEFAULTS)->map(fn ($v, $k) => Setting::get($k))->all(),
            ['work_days' => [1, 2, 3], 'quiet_hours_enabled' => '1']
        ))->assertSessionHasNoErrors();
        $this->assertSame('1,2,3', Setting::get('work_days'));
    }
}
