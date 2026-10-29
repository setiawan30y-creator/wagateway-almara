<?php

namespace Tests\Feature;

use App\Models\AiLog;
use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\KnowledgeItem;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\InboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** AI Agent (OpenRouter) — memakai respons palsu. @2026-Alifiandra */
class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'owner']);
        $this->device = Device::create(['name' => 'Simulasi', 'provider' => 'log', 'credentials' => [], 'is_default' => true, 'is_active' => true]);
        Setting::set('ai_enabled', '1');
        Setting::setSecret('ai_api_key', 'sk-or-test');
        Setting::set('ai_mode', 'always');
        KnowledgeItem::create(['title' => 'Jam operasional', 'content' => 'Buka Senin-Sabtu 08.00-17.00 WIB.', 'is_active' => true]);
    }

    private function fakeAi(string $answer): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'google/gemma-4-31b-it:free',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $answer]]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 20],
        ])]);
    }

    private function receive(string $text, string $from = '081277770000'): void
    {
        app(InboundService::class)->receive($this->device, $from, $text, null, 'Dewi Lestari');
        app()->terminate(); // menjalankan job AI (dispatchAfterResponse)
    }

    public function test_ai_replies_from_knowledge(): void
    {
        $this->fakeAi('Kami buka Senin–Sabtu pukul 08.00–17.00 WIB, kak.');
        $this->receive('jam buka kapan ya?');

        $reply = Message::where('direction', 'out')->first();
        $this->assertNotNull($reply);
        $this->assertTrue($reply->sent_by_ai);
        $this->assertStringContainsString('08.00', $reply->body);

        Http::assertSent(function (Request $r) {
            $system = $r['messages'][0]['content'];

            return str_ends_with($r->url(), '/chat/completions')
                && $r->hasHeader('Authorization', 'Bearer sk-or-test')
                && $r['model'] === 'openrouter/free'
                && str_contains($system, 'Jam operasional')
                && str_contains($system, 'Dewi Lestari')
                && end($r->data()['messages'])['content'] === 'jam buka kapan ya?';
        });

        $this->assertSame(1, AiLog::where('status', 'ok')->count());
        $this->assertSame(140, (int) AiLog::sum('prompt_tokens') + (int) AiLog::sum('completion_tokens'));
    }

    public function test_handoff_when_ai_does_not_know_or_customer_asks_human(): void
    {
        $this->fakeAi('[HANDOFF]');
        $this->receive('bisa kirim ke luar negeri?');

        $conv = Conversation::first();
        $this->assertTrue($conv->aiPaused());
        $this->assertSame('high', $conv->priority);
        $this->assertTrue(Message::where('direction', 'out')->where('body', Setting::get('ai_handoff_message'))->exists());
        $this->assertDatabaseHas('app_notifications', ['type' => 'ai.handoff']);
        $this->assertSame(1, $conv->notes()->count());

        // Kata kunci "admin" -> langsung handoff tanpa memanggil AI
        Http::fake();
        $this->receive('saya mau bicara dengan admin', '081288880000');
        Http::assertNothingSent();
        $this->assertTrue(Conversation::whereHas('contact', fn ($q) => $q->where('phone', '6281288880000'))->first()->aiPaused());
    }

    public function test_ai_pauses_after_human_reply_and_respects_toggles(): void
    {
        $this->fakeAi('Halo kak!');
        $this->receive('halo');
        $conv = Conversation::first();

        // CS manusia membalas -> AI dijeda
        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/balas", ['body' => 'Saya bantu ya kak'])->assertRedirect();
        $this->assertTrue($conv->refresh()->aiPaused());

        $before = Message::where('sent_by_ai', true)->count();
        $this->receive('oke terima kasih');
        $this->assertSame($before, Message::where('sent_by_ai', true)->count());

        // aktifkan lagi lewat inbox lalu matikan untuk chat ini
        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/ai", ['enable' => 1])->assertRedirect();
        $this->assertFalse($conv->refresh()->aiPaused());
        $this->actingAs($this->admin)->post("/inbox/{$conv->id}/ai", ['enable' => 0]);
        $this->assertFalse($conv->refresh()->ai_enabled);
    }

    public function test_auto_reply_has_priority_and_opted_out_ignored_and_limits(): void
    {
        $this->fakeAi('Jawaban AI');
        AutoReply::create(['name' => 'Harga', 'trigger' => 'keyword', 'match_type' => 'contains', 'keywords' => 'harga', 'reply' => 'Daftar harga terlampir.', 'cooldown_minutes' => 0, 'priority' => 1, 'is_active' => true]);

        $this->receive('berapa harga paket?');
        $this->assertSame(0, Message::where('sent_by_ai', true)->count());
        Http::assertNothingSent();

        Contact::first()->setConsent('opted_out', 'manual');
        $this->receive('halo lagi');
        Http::assertNothingSent();

        Setting::set('ai_daily_limit', '0');
        $this->receive('pesan baru', '081299990000');
        Http::assertNothingSent();
    }

    public function test_disabled_ai_does_nothing(): void
    {
        Setting::set('ai_enabled', '0');
        Http::fake();
        $this->receive('halo');
        Http::assertNothingSent();
        $this->assertSame(0, Message::where('direction', 'out')->count());
    }

    public function test_panel_settings_knowledge_test_and_suggest(): void
    {
        $this->actingAs($this->admin)->get('/ai-agent')->assertOk()->assertSee('Basis pengetahuan')->assertDontSee('sk-or-test');

        $this->actingAs($this->admin)->put('/ai-agent', [
            'ai_enabled' => '1', 'ai_api_key' => 'sk-or-baru', 'ai_base_url' => 'https://openrouter.ai/api/v1', 'ai_model' => 'qwen/qwen3.8-27b:free',
            'ai_agent_name' => 'Asisten Toko', 'ai_persona' => 'Ramah.', 'ai_mode' => 'always', 'ai_handoff_keywords' => 'cs', 'ai_handoff_message' => 'Tunggu ya',
            'ai_pause_minutes' => 30, 'ai_max_per_contact_hour' => 5, 'ai_daily_limit' => 500, 'ai_temperature' => 0.3,
        ])->assertSessionHas('success');
        $this->assertSame('sk-or-baru', Setting::secret('ai_api_key'));
        $this->assertNotSame('sk-or-baru', Setting::get('ai_api_key')); // terenkripsi
        $this->assertSame('qwen/qwen3.8-27b:free', Setting::get('ai_model'));

        $this->actingAs($this->admin)->post('/ai-agent/pengetahuan', ['title' => 'Alamat', 'content' => 'Jl. Contoh No. 1'])->assertRedirect();
        $this->assertSame(2, KnowledgeItem::count());

        $this->fakeAi('Buka jam 08.00 kak.');
        $this->actingAs($this->admin)->postJson('/ai-agent/uji', ['question' => 'jam buka?'])->assertJson(['ok' => true, 'answer' => 'Buka jam 08.00 kak.']);

        $this->receive('kapan buka?');
        $conv = Conversation::first();
        $agent = User::factory()->create(['role' => 'agent']);
        $this->actingAs($agent)->postJson("/inbox/{$conv->id}/ai-saran")->assertJson(['ok' => true]);
        $this->actingAs($agent)->get('/ai-agent')->assertForbidden();

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429)]);
        $this->actingAs($this->admin)->postJson('/ai-agent/uji', ['question' => 'tes'])->assertStatus(502)->assertJsonPath('ok', false);
        $this->assertTrue(AiLog::where('status', 'error')->where('error', 'like', '%Rate limit%')->exists());

        $this->actingAs($this->admin)->delete('/ai-agent/api-key')->assertRedirect();
        $this->assertSame('', Setting::secret('ai_api_key'));
    }

    public function test_retrieval_picks_relevant_items_for_large_knowledge(): void
    {
        foreach (range(1, 12) as $i) {
            KnowledgeItem::create(['title' => "Produk lain {$i}", 'content' => str_repeat('deskripsi umum barang ', 40), 'is_active' => true]);
        }
        KnowledgeItem::create(['title' => 'Ongkos kirim', 'content' => 'Ongkir Jabodetabek Rp10.000, luar kota Rp25.000.', 'is_active' => true]);

        $found = app(\App\AI\AiAgent::class)->retrieve('berapa ongkir ke bandung?');
        $this->assertSame('Ongkos kirim', $found->first()->title);
        $this->assertLessThanOrEqual(5, $found->count());
    }
}
