<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @2026-Alifiandra */
class ApiMessageTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        [, $this->key] = ApiKey::generate($user, 'Test');

        Device::create(['name' => 'Simulasi', 'provider' => 'log', 'credentials' => [], 'is_default' => true, 'is_active' => true]);
    }

    public function test_rejects_request_without_api_key(): void
    {
        $this->postJson('/api/v1/messages/send', ['to' => '081234567890', 'message' => 'Halo'])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_sends_message_with_log_provider(): void
    {
        $this->withToken($this->key)
            ->postJson('/api/v1/messages/send', ['to' => '081234567890', 'message' => 'Halo'])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.to', '6281234567890')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonStructure(['data' => ['id'], 'meta' => ['request_id']]);
    }

    public function test_validation_error_envelope(): void
    {
        $this->withToken($this->key)
            ->postJson('/api/v1/messages/send', ['message' => 'Halo'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['to']]]);
    }

    public function test_idempotency_key_prevents_duplicate(): void
    {
        $payload = ['to' => '081234567890', 'message' => 'Halo'];
        $headers = ['Idempotency-Key' => 'abc-123'];

        $first = $this->withToken($this->key)->withHeaders($headers)->postJson('/api/v1/messages/send', $payload)->assertStatus(202);
        $second = $this->withToken($this->key)->withHeaders($headers)->postJson('/api/v1/messages/send', $payload)->assertStatus(200);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('messages', 1);

        $this->withToken($this->key)->withHeaders($headers)
            ->postJson('/api/v1/messages/send', ['to' => '081234567890', 'message' => 'Beda'])
            ->assertStatus(409);
    }

    public function test_revoked_key_is_rejected(): void
    {
        ApiKey::query()->update(['revoked_at' => now()]);

        $this->withToken($this->key)->getJson('/api/v1/devices')->assertStatus(401);
    }

    public function test_lists_devices_without_credentials(): void
    {
        $this->withToken($this->key)->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'log')
            ->assertJsonMissingPath('data.0.credentials');
    }
}
