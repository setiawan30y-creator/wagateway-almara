<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Services\MessageService;
use App\Support\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Paket Gratis / Pro & kode lisensi bertanda tangan. @2026-Alifiandra */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lic-'.uniqid();
        mkdir($this->dir);
        config([
            'license.force_plan' => null,
            'license.public_key_path' => $this->dir.'/public.pem',
            'license.private_key_path' => $this->dir.'/private.pem',
        ]);
        \App\Models\Tenant::query()->update(['plan' => 'free']); // mulai dari paket Gratis
        License::flush();
        $this->admin = User::factory()->create(['role' => 'owner', 'is_superadmin' => true]);
    }

    protected function tearDown(): void
    {
        License::flush();
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function issue(array $opts = []): string
    {
        Artisan::call('license:issue', ['name' => 'Toko Contoh'] + $opts);
        preg_match('/^([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)$/m', Artisan::output(), $m);

        return $m[1];
    }

    public function test_free_plan_watermark_limits_and_locked_features(): void
    {
        $this->assertSame('free', License::plan());

        $sent = [];
        Http::fake(function (Request $r) use (&$sent) {
            $sent[] = $r->data();

            return Http::response(['success' => true, 'id' => 'W'.count($sent)]);
        });
        config(['whatsapp.engine.url' => 'http://engine.test']);
        $device = Device::create(['name' => 'WA', 'provider' => 'qr', 'is_active' => true, 'is_default' => true, 'status' => 'connected', 'credentials' => []]);

        // watermark di pesan teks
        app(MessageService::class)->queueText($device, '081300000001', 'Halo kak', $this->admin);
        $this->assertSame('Halo kak'.config('license.watermark_text'), $sent[0]['message']);
        $this->assertSame('Halo kak', Message::first()->body); // riwayat tetap teks asli

        // batas 1 nomor
        $this->actingAs($this->admin)->post('/perangkat/scan', ['name' => 'Nomor kedua'])->assertRedirect('/perangkat')->assertSessionHas('warning');
        $this->assertSame(1, Device::count());

        // broadcast & AI terkunci
        $this->actingAs($this->admin)->get('/broadcast/buat')->assertRedirect('/lisensi');
        $this->actingAs($this->admin)->get('/broadcast')->assertOk()->assertSee('hanya tersedia di paket');
        $this->actingAs($this->admin)->put('/ai-agent', [])->assertRedirect('/lisensi');
        $this->assertFalse(app(\App\AI\AiAgent::class)->isConfigured());

        // batas 100 pesan/hari
        config(['license.plans.free.daily_messages' => 3]);
        License::flush();
        foreach (range(2, 4) as $i) {
            app(MessageService::class)->queueText($device, '08130000000'.$i, 'Pesan '.$i, $this->admin);
        }
        $last = Message::orderByDesc('id')->first();
        $this->assertSame('failed', $last->status);
        $this->assertStringContainsString('Batas 3 pesan/hari', $last->error);

        $this->actingAs($this->admin)->get('/lisensi')->assertOk()->assertSee('Gratis')->assertSee('Perbandingan paket');
    }

    public function test_issue_activate_pro_and_expiry(): void
    {
        Artisan::call('license:keygen');
        $this->assertFileExists($this->dir.'/private.pem');
        $this->assertFileExists($this->dir.'/public.pem');

        $key = $this->issue(['--days' => 30, '--devices' => 3]);

        $this->actingAs($this->admin)->post('/lisensi', ['key' => 'rusak.'.$key])->assertSessionHasErrors('key');
        $this->actingAs($this->admin)->post('/lisensi', ['key' => "  {$key}\n"])->assertRedirect('/lisensi')->assertSessionHas('success');

        License::flush();
        $this->assertTrue(License::isPro());
        $this->assertSame(3, License::limit('devices'));
        $this->assertNull(License::watermark());
        $this->assertStringStartsWith('LIC-', (string) License::current()['id']);
        $this->actingAs($this->admin)->get('/broadcast/buat')->assertOk();

        // kode diubah (mis. plan / devices dipalsukan) -> tidak sah
        [$body, $sig] = explode('.', $key);
        $payload = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $payload['devices'] = 999;
        $forged = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=').'.'.$sig;
        $this->assertNull(License::verify($forged));

        // kedaluwarsa + masa tenggang 3 hari -> kembali Gratis
        $this->travel(32)->days();
        License::flush();
        $this->assertTrue(License::isPro());
        $this->assertStringContainsString('masa tenggang', License::current()['warning']);
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Segera perpanjang');
        $this->travel(3)->days();
        License::flush();
        $this->assertSame('free', License::plan());
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('kembali ke paket Gratis');

        // kode yang sama tidak bisa dipakai akun usaha lain
        $other = \App\Models\Tenant::create(['name' => 'Usaha Lain']);
        $this->assertStringContainsString('sudah dipakai', License::applyKey($other, $key));

        // kembalikan ke Gratis (super-admin)
        $this->actingAs($this->admin)->delete('/lisensi')->assertRedirect('/lisensi');
        License::flush();
        $this->assertSame('free', License::plan());
    }

    public function test_api_campaign_requires_pro(): void
    {
        [, $apiKey] = \App\Models\ApiKey::generate($this->admin, 'Test');
        $tpl = Template::create(['name' => 'T', 'category' => 'marketing', 'is_active' => true]);

        $this->withToken($apiKey)->postJson('/api/v1/campaigns', ['name' => 'X', 'template_id' => $tpl->id])
            ->assertStatus(403)->assertJsonPath('error.code', 'PLAN_REQUIRED');
    }

    public function test_keygen_refuses_to_overwrite(): void
    {
        Artisan::call('license:keygen');
        $this->assertSame(1, Artisan::call('license:keygen'));
    }
}
