<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Token API per perangkat (gaya Fonnte) + key bisa ditampilkan & disalin lagi. @2026-Alifiandra */
class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_page_creates_copyable_token_bound_to_device(): void
    {
        $admin = User::factory()->create(['role' => 'owner']);
        $main = Device::create(['name' => 'Utama', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);
        $cs = Device::create(['name' => 'WA CS', 'provider' => 'log', 'is_active' => true]);

        $this->actingAs($admin)->get('/perangkat')->assertOk()
            ->assertSee('Token API')->assertSee('data-copy="#tok-'.$cs->id.'"', false)->assertSee(url('/api/send'));

        $token = ApiKey::active()->where('device_id', $cs->id)->first();
        $plain = $token->plainKey();
        $this->assertStringStartsWith('alm_', $plain);
        $this->actingAs($admin)->get('/perangkat')->assertSee($plain); // ditampilkan penuh (disamarkan via CSS)
        $this->assertSame(2, ApiKey::count()); // tidak dibuat ulang tiap buka halaman

        // token perangkat -> pesan keluar dari perangkat itu, bukan nomor utama
        $this->postJson('/api/send', ['target' => '081234567890', 'message' => 'Halo'], ['Authorization' => $plain])->assertOk()->assertJsonPath('status', true);
        $this->assertSame($cs->id, Message::latest('id')->first()->device_id);
        $this->withToken($plain)->postJson('/api/v1/messages/send', ['to' => '081234567891', 'message' => 'Hai'])->assertStatus(202);
        $this->assertSame($cs->id, Message::latest('id')->first()->device_id);
        // device_id eksplisit tetap dihormati
        $this->postJson('/api/send', ['target' => '081234567892', 'message' => 'X', 'device_id' => $main->id], ['Authorization' => $plain])->assertOk();
        $this->assertSame($main->id, Message::latest('id')->first()->device_id);

        // buat ulang -> token lama tidak berlaku
        $this->actingAs($admin)->post("/perangkat/{$cs->id}/token")->assertRedirect('/perangkat');
        $this->postJson('/api/send', ['target' => '081234567890', 'message' => 'Halo'], ['Authorization' => $plain])->assertStatus(401);
        $new = ApiKey::active()->where('device_id', $cs->id)->first();
        $this->assertNotSame($plain, $new->plainKey());

        // hapus perangkat -> tokennya dicabut
        $this->actingAs($admin)->delete("/perangkat/{$cs->id}")->assertRedirect();
        $this->assertNotNull($new->refresh()->revoked_at);
    }

    public function test_api_key_page_shows_full_key_with_copy(): void
    {
        $admin = User::factory()->create(['role' => 'owner']);
        $d = Device::create(['name' => 'WA Toko', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);

        $this->actingAs($admin)->post('/api-key', ['name' => 'Aplikasi Kasir', 'device_id' => $d->id])->assertRedirect();
        $key = ApiKey::where('name', 'Aplikasi Kasir')->first();
        $this->assertSame($d->id, $key->device_id);
        $this->assertNull($key->toArray()['secret'] ?? null); // tidak bocor di JSON

        $this->actingAs($admin)->get('/api-key')->assertOk()
            ->assertSee($key->plainKey())->assertSee('data-copy="#key-'.$key->id.'"', false)->assertSee('WA Toko');

        // key lama (hanya hash) tetap berfungsi tapi tidak bisa ditampilkan
        $old = ApiKey::create(['user_id' => $admin->id, 'name' => 'Lama', 'prefix' => 'alm_abcdef', 'key_hash' => ApiKey::hash('alm_abcdef123')]);
        $this->assertNull($old->plainKey());
        $this->actingAs($admin)->get('/api-key')->assertOk()->assertSee('Key lama');
    }
}
