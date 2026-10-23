<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** @2026-Alifiandra */
class WebPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('ALMARA GATEWAY WA');
    }

    public function test_user_can_login_and_see_pages(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect('/dashboard');

        foreach (['/dashboard', '/kirim', '/pesan', '/perangkat', '/perangkat/tambah', '/api-key', '/akun'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_send_form_creates_messages(): void
    {
        $user = User::factory()->create();
        $device = Device::create(['name' => 'Simulasi', 'provider' => 'log', 'credentials' => [], 'is_default' => true, 'is_active' => true]);

        $this->actingAs($user)
            ->post('/kirim', ['device_id' => $device->id, 'to' => "081234567890\n081298765432", 'message' => 'Halo'])
            ->assertRedirect('/pesan');

        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseHas('messages', ['to' => '6281298765432', 'status' => 'sent']);

        $this->actingAs($user)->get('/pesan')->assertOk()->assertSee('6281•••890');
    }

    public function test_device_credentials_are_encrypted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/perangkat', [
            'name' => 'Meta',
            'provider' => 'meta',
            'credentials' => ['phone_number_id' => '123456', 'access_token' => 'token-rahasia'],
            'is_active' => '1',
        ])->assertRedirect('/perangkat');

        $raw = DB::table('devices')->where('name', 'Meta')->value('credentials');
        $this->assertStringNotContainsString('token-rahasia', $raw);
        $this->assertSame('token-rahasia', Device::where('name', 'Meta')->first()->credential('access_token'));
    }
}
