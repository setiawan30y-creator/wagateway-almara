<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Endpoint kompatibel aplikasi lain (gaya Fonnte / Wablas) & variasi pengiriman token. @2026-Alifiandra */
class CompatApiTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        Device::create(['name' => 'Sim', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);
        [, $this->key] = ApiKey::generate(User::factory()->create(), 'Billing');
    }

    public function test_token_variants_are_accepted(): void
    {
        $body = ['to' => '081300000001', 'message' => 'Tes'];
        $this->withHeaders(['Authorization' => $this->key])->postJson('/api/v1/messages/send', $body)->assertStatus(202);
        $this->postJson('/api/v1/messages/send?token='.$this->key, $body)->assertStatus(202);
        $this->postJson('/api/v1/messages/send', $body + ['api_key' => $this->key])->assertStatus(202);
        $this->withHeaders(['Authorization' => 'salah'])->postJson('/api/v1/messages/send', $body)->assertStatus(401);
    }

    public function test_fonnte_style(): void
    {
        $this->withHeaders(['Authorization' => $this->key])
            ->post('/api/send', ['target' => '081300000001,081300000002', 'message' => 'Tagihan bulan ini Rp150.000'])
            ->assertOk()->assertJsonPath('status', true)->assertJsonCount(2, 'id')->assertJsonPath('target.0', '6281300000001');
        $this->assertSame(2, Message::where('body', 'Tagihan bulan ini Rp150.000')->count());

        $this->withHeaders(['Authorization' => $this->key])->post('/api/send', ['target' => '', 'message' => 'x'])
            ->assertStatus(422)->assertJsonPath('status', false);
    }

    public function test_wablas_style_post_and_get(): void
    {
        $this->withHeaders(['Authorization' => $this->key])->post('/api/send-message', ['phone' => '081300000003', 'message' => 'Halo'])
            ->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.messages.0.phone', '6281300000003');

        $this->get('/api/send-message?token='.$this->key.'&phone=081300000004&message=Halo+GET')
            ->assertOk()->assertJsonPath('status', true);
        $this->assertTrue(Message::where('to', '6281300000004')->where('body', 'Halo GET')->exists());
    }
}
