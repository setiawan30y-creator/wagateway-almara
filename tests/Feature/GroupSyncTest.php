<?php

namespace Tests\Feature;

use App\Models\AutoReply;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Models\WaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** v0.6: sinkron kontak & riwayat, grup, broadcast dengan lampiran. @2026-Alifiandra */
class GroupSyncTest extends TestCase
{
    use RefreshDatabase;

    private const GJID = '120363025555555555@g.us';

    private function qrDevice(): Device
    {
        config(['whatsapp.engine.url' => 'http://engine.test', 'whatsapp.engine.token' => 'tok']);

        return Device::create([
            'name' => 'WA CS', 'provider' => 'qr', 'is_active' => true, 'is_default' => true, 'status' => 'connected',
            'phone_number' => '6281200000000', 'credentials' => ['webhook_token' => 'wh-secret'],
        ]);
    }

    private function hook(Device $device, array $payload)
    {
        return $this->withHeaders(['X-Webhook-Token' => 'wh-secret'])->postJson("/api/webhooks/qr/{$device->id}", $payload);
    }

    public function test_contacts_and_history_from_engine_webhook(): void
    {
        $device = $this->qrDevice();
        Contact::create(['phone' => '6281311111111', 'name' => 'Nama Tim', 'consent' => 'opted_in']);

        $this->hook($device, ['type' => 'contacts', 'contacts' => [
            ['phone' => '6281311111111', 'name' => 'Nama di HP'],   // tidak menimpa nama yang ada
            ['phone' => '6281322222222', 'name' => 'Sari'],
            ['phone' => '12', 'name' => 'tidak valid'],
        ]])->assertOk()->assertJson(['created' => 1]);

        $this->assertSame('Nama Tim', Contact::where('phone', '6281311111111')->value('name'));
        $sari = Contact::where('phone', '6281322222222')->first();
        $this->assertSame('wa-sync', $sari->source);
        $this->assertSame('unknown', $sari->consent);

        AutoReply::create(['name' => 'Halo', 'match_type' => 'contains', 'keywords' => 'halo', 'reply' => 'Hai juga', 'is_active' => true]);

        $history = [
            ['id' => 'H1', 'from' => '6281322222222', 'from_me' => false, 'message' => 'halo kak', 'timestamp' => now()->subDays(2)->timestamp, 'name' => 'Sari'],
            ['id' => 'H2', 'from' => '6281322222222', 'from_me' => true, 'message' => 'iya kak', 'timestamp' => now()->subDays(2)->addMinute()->timestamp],
            ['id' => 'H3', 'from' => '6281700000001', 'from_me' => false, 'message' => 'info grup', 'timestamp' => now()->subDay()->timestamp, 'name' => 'Andi', 'group' => ['jid' => self::GJID, 'subject' => 'Reseller']],
        ];
        $this->hook($device, ['type' => 'history', 'messages' => $history])->assertOk()->assertJson(['saved' => 3]);
        $this->hook($device, ['type' => 'history', 'messages' => $history])->assertOk()->assertJson(['saved' => 0]); // dedupe

        // riwayat tidak memicu auto-reply / notifikasi dan tidak dihitung belum dibaca
        $this->assertSame(0, Message::where('direction', 'out')->where('status', 'queued')->count());
        $this->assertDatabaseMissing('app_notifications', ['type' => 'message.received']);
        $conv = Conversation::where('contact_id', $sari->id)->first();
        $this->assertSame(0, $conv->unread_count);
        $this->assertSame('iya kak', $conv->last_message_preview);
        $this->assertTrue(Message::where('provider_message_id', 'H1')->first()->created_at->lt(now()->subDay()));

        $group = WaGroup::where('jid', self::GJID)->first();
        $this->assertSame('Reseller', $group->subject);
        $this->assertSame('Andi', Message::where('provider_message_id', 'H3')->value('participant_name'));
    }

    public function test_group_message_inbox_reply_and_sync(): void
    {
        $device = $this->qrDevice();
        $sent = [];

        Http::fake(function (Request $r) use (&$sent) {
            $path = parse_url($r->url(), PHP_URL_PATH);

            if (in_array($path, ['/send-message', '/send-media'], true)) {
                $sent[] = $r->data();

                return Http::response(['success' => true, 'id' => 'OUT'.count($sent)]);
            }

            return match ($path) {
                '/groups' => Http::response(['success' => true, 'groups' => [[
                    'jid' => self::GJID, 'subject' => 'Reseller Jatim', 'size' => 3, 'is_admin' => true, 'announce' => false,
                    'participants' => [['phone' => '6281200000000', 'admin' => 'superadmin'], ['phone' => '6281700000001', 'admin' => null], ['phone' => '6281700000002', 'admin' => 'admin']],
                ]]]),
                '/contacts' => Http::response(['success' => true, 'contacts' => [['phone' => '6281800000001', 'name' => 'Budi HP'], ['phone' => '6281800000002', 'name' => null]]]),
                default => Http::response([], 404),
            };
        });

        AutoReply::create(['name' => 'Halo', 'match_type' => 'contains', 'keywords' => 'halo', 'reply' => 'Hai juga', 'is_active' => true]);

        // pesan grup masuk: tidak memicu auto-reply / STOP
        $this->hook($device, ['type' => 'message', 'id' => 'G1', 'from' => '6281700000001', 'name' => 'Andi', 'message' => 'halo semua', 'group' => ['jid' => self::GJID, 'subject' => 'Reseller']])
            ->assertOk();
        $this->hook($device, ['type' => 'message', 'id' => 'G2', 'from' => '6281700000001', 'message' => 'STOP', 'group' => ['jid' => self::GJID]])->assertOk();
        $this->assertCount(0, $sent);
        $this->assertSame(0, Contact::count());

        $conv = Conversation::whereNotNull('group_id')->first();
        $this->assertNull($conv->contact_id);
        $this->assertSame(2, $conv->unread_count);

        $admin = User::factory()->create(['role' => 'owner']);
        $this->actingAs($admin)->get('/inbox?filter=groups')->assertOk()->assertSee('Reseller');
        $this->actingAs($admin)->get("/inbox/{$conv->id}")->assertOk()->assertSee('Andi')->assertSee('halo semua')->assertDontSee('data-ai-suggest', false);

        // balas di inbox -> terkirim ke JID grup
        $this->actingAs($admin)->post("/inbox/{$conv->id}/balas", ['body' => 'Halo reseller'])->assertRedirect();
        $this->assertSame(self::GJID, $sent[0]['to']);
        $out = Message::where('direction', 'out')->first();
        $this->assertSame('sent', $out->status);
        $this->assertSame($conv->group_id, $out->group_id);

        // sinkron grup + anggota
        $this->actingAs($admin)->post('/grup/sinkron')->assertRedirect()->assertSessionHas('success');
        $group = WaGroup::first();
        $this->assertSame('Reseller Jatim', $group->subject);
        $this->assertCount(3, $group->participants);
        $this->assertTrue($group->is_admin);
        $this->actingAs($admin)->get('/grup')->assertOk()->assertSee('Reseller Jatim');
        $this->actingAs($admin)->get("/grup/{$group->id}")->assertOk()->assertSee('+6281700000002');

        // simpan anggota (tanpa nomor sendiri) ke kontak + tag
        $this->actingAs($admin)->post("/grup/{$group->id}/impor", ['tag' => 'reseller'])->assertRedirect();
        $this->assertSame(2, Contact::count());
        $this->assertSame(2, \App\Models\Tag::where('name', 'reseller')->first()->contacts()->count());

        // kirim lampiran ke grup dari halaman grup
        Storage::fake('local');
        $this->actingAs($admin)->post("/grup/{$group->id}/kirim", ['body' => 'Katalog', 'file' => UploadedFile::fake()->create('katalog.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->assertSame(self::GJID, end($sent)['to']);
        $this->assertSame('document', end($sent)['type']);

        // sinkron kontak dari HP
        $this->actingAs($admin)->post('/kontak/sinkron-hp')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Budi HP', Contact::where('phone', '6281800000001')->value('name'));
        $this->assertSame(4, Contact::count());
    }

    public function test_api_can_send_to_group(): void
    {
        $device = Device::create(['name' => 'Sim', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);
        WaGroup::create(['device_id' => $device->id, 'jid' => self::GJID, 'subject' => 'Tim']);
        [, $key] = \App\Models\ApiKey::generate(User::factory()->create(), 'Test');

        $this->withToken($key)->postJson('/api/v1/messages/send', ['to' => self::GJID, 'message' => 'Rapat jam 3'])
            ->assertStatus(202)->assertJsonPath('data.to', self::GJID)->assertJsonPath('data.group.jid', self::GJID);
        $this->withToken($key)->postJson('/api/v1/messages/send', ['to' => '999@g.us', 'message' => 'x'])
            ->assertStatus(404)->assertJsonPath('error.code', 'GROUP_NOT_FOUND');
    }

    public function test_meta_device_cannot_send_to_group(): void
    {
        $result = (new \App\WhatsApp\Providers\MetaCloudProvider)->sendText(
            Device::create(['name' => 'Meta', 'provider' => 'meta', 'is_active' => true, 'credentials' => ['phone_number_id' => '1', 'access_token' => 'x']]),
            self::GJID, 'hai');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Scan QR', $result->error);
    }

    public function test_broadcast_with_attachment(): void
    {
        Storage::fake('local');
        $device = Device::create(['name' => 'Sim', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);
        $template = Template::create(['name' => 'Promo', 'is_active' => true]);
        $template->variants()->create(['label' => 'A', 'body' => 'Halo {{name}}, cek brosur ya']);
        Contact::create(['phone' => '6281111111111', 'name' => 'Ani', 'consent' => 'opted_in']);
        Contact::create(['phone' => '6281111111112', 'name' => 'Banu', 'consent' => 'opted_in']);

        $admin = User::factory()->create(['role' => 'owner']);
        $this->actingAs($admin)->post('/broadcast', [
            'name' => 'Brosur', 'device_id' => $device->id, 'template_id' => $template->id, 'rate_per_minute' => 30, 'mode' => 'now',
            'file' => UploadedFile::fake()->image('brosur.jpg', 400, 300),
        ])->assertRedirect();

        $campaign = Campaign::first();
        $this->assertSame('image', $campaign->media_type);
        $messages = Message::where('campaign_id', $campaign->id)->get();
        $this->assertCount(2, $messages);
        $this->assertTrue($messages->every(fn ($m) => $m->media_type === 'image' && $m->media_path === $campaign->media_path && $m->status === 'sent'));
        $this->assertSame('Halo Ani, cek brosur ya', $messages->firstWhere('to', '6281111111111')->body);

        $this->actingAs($admin)->get("/broadcast/{$campaign->id}")->assertOk()->assertSee('brosur.jpg');
    }
}
