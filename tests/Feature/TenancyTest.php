<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\License;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Multi-penyewa: isolasi data, panel super-admin, penangguhan, pengingat, pendaftaran. @2026-Alifiandra */
class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.force_plan' => null]);
        $this->super = User::factory()->create(['role' => 'owner', 'is_superadmin' => true]); // penyewa #1 (usaha utama)
        Tenancy::forget();
    }

    /** Buat penyewa lewat panel super-admin. */
    private function newTenant(string $name, string $email, array $extra = []): Tenant
    {
        $this->actingAs($this->super)->post('/admin/penyewa', $extra + [
            'name' => $name, 'plan' => 'pro', 'expires_at' => now()->addDays(30)->toDateString(),
            'owner_name' => 'Pemilik '.$name, 'owner_email' => $email, 'owner_password' => 'rahasia123', 'contact_phone' => '081299990000',
        ])->assertRedirect();
        Tenancy::forget();

        return Tenant::where('name', $name)->firstOrFail();
    }

    public function test_data_is_isolated_between_tenants(): void
    {
        $a = $this->newTenant('Usaha A', 'a@contoh.test');
        $b = $this->newTenant('Usaha B', 'b@contoh.test');
        $ua = User::withoutGlobalScopes()->where('email', 'a@contoh.test')->first();
        $ub = User::withoutGlobalScopes()->where('email', 'b@contoh.test')->first();

        // nomor yang sama boleh ada di dua penyewa
        $this->actingAs($ua)->post('/kontak', ['phone' => '6281311112222', 'name' => 'Pelanggan A', 'stage' => 'new', 'consent' => 'unknown'])->assertRedirect()->assertSessionHasNoErrors();
        Tenancy::forget();
        $this->actingAs($ub)->post('/kontak', ['phone' => '6281311112222', 'name' => 'Pelanggan B', 'stage' => 'new', 'consent' => 'unknown'])->assertRedirect()->assertSessionHasNoErrors();
        Tenancy::forget();

        $ca = Contact::withoutGlobalScopes()->where('tenant_id', $a->id)->first();
        $cb = Contact::withoutGlobalScopes()->where('tenant_id', $b->id)->first();
        $this->assertSame('Pelanggan A', $ca->name);
        $this->assertSame('Pelanggan B', $cb->name);

        // A tidak melihat / tidak bisa membuka data B
        $this->actingAs($ua)->get('/kontak')->assertOk()->assertSee('Pelanggan A')->assertDontSee('Pelanggan B');
        Tenancy::forget();
        $this->actingAs($ua)->get("/kontak/{$cb->id}")->assertNotFound();
        Tenancy::forget();
        $this->actingAs($ua)->get('/pengguna')->assertOk()->assertDontSee('b@contoh.test');
        Tenancy::forget();

        // pengaturan terpisah
        Tenancy::run($a, fn () => Setting::set('stop_keywords', 'stop-a'));
        $this->assertSame('stop-a', Tenancy::run($a, fn () => Setting::get('stop_keywords')));
        $this->assertNotSame('stop-a', Tenancy::run($b, fn () => Setting::get('stop_keywords')));

        // API key hanya melihat data penyewanya
        [, $key] = Tenancy::run($a, fn () => ApiKey::generate($ua, 'A'));
        Tenancy::forget();
        $this->withToken($key)->getJson('/api/v1/contacts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Pelanggan A');

        // super-admin tidak bisa diakses penyewa biasa
        Tenancy::forget();
        $this->actingAs($ua)->get('/admin/penyewa')->assertForbidden();
    }

    public function test_superadmin_panel_payment_impersonate_and_suspend(): void
    {
        $t = $this->newTenant('Toko Contoh', 'owner@contoh.test', ['plan' => 'free', 'expires_at' => null]);
        $this->assertSame(1, User::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(1, Device::withoutGlobalScopes()->where('tenant_id', $t->id)->where('provider', 'log')->count());

        $this->actingAs($this->super)->get('/admin/penyewa')->assertOk()->assertSee('Toko Contoh');
        Tenancy::forget();

        // catat pembayaran -> Pro 30 hari
        $this->actingAs($this->super)->post("/admin/penyewa/{$t->id}/bayar", ['plan' => 'pro', 'days' => 30, 'amount' => 150000, 'note' => 'Transfer'])->assertRedirect();
        $t->refresh();
        $this->assertSame('pro', $t->plan);
        $this->assertSame(now()->addDays(30)->toDateString(), $t->expires_at->toDateString());
        $this->assertSame(150000, (int) $t->payments()->sum('amount'));
        Tenancy::forget();
        $this->actingAs($this->super)->get("/admin/penyewa/{$t->id}")->assertOk()->assertSee('150.000');
        Tenancy::forget();

        // masuk ke akun penyewa -> melihat data penyewa itu
        Tenancy::run($t, fn () => Contact::create(['phone' => '6281355556666', 'name' => 'Kontak Penyewa']));
        $this->actingAs($this->super)->post("/admin/penyewa/{$t->id}/masuk")->assertRedirect('/dashboard');
        Tenancy::forget();
        $this->actingAs($this->super)->get('/kontak')->assertOk()->assertSee('Kontak Penyewa')->assertSee('sebagai super-admin');
        Tenancy::forget();
        $this->actingAs($this->super)->post('/admin/keluar-penyewa')->assertRedirect();
        Tenancy::forget();
        $this->actingAs($this->super)->get('/kontak')->assertOk()->assertDontSee('Kontak Penyewa');
        Tenancy::forget();

        // tangguhkan -> pemilik tidak bisa memakai panel, API ditolak
        $this->actingAs($this->super)->post("/admin/penyewa/{$t->id}/status")->assertRedirect();
        $this->assertSame('suspended', $t->refresh()->status);
        Tenancy::forget();
        $owner = User::withoutGlobalScopes()->where('email', 'owner@contoh.test')->first();
        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/login');
        Tenancy::forget();
        [, $key] = Tenancy::run($t, fn () => ApiKey::generate($owner, 'K'));
        Tenancy::forget();
        $this->withToken($key)->getJson('/api/v1/contacts')->assertStatus(403)->assertJsonPath('error.code', 'TENANT_SUSPENDED');

        // usaha utama tidak bisa ditangguhkan
        Tenancy::forget();
        $this->actingAs($this->super)->post('/admin/penyewa/'.Tenant::defaultId().'/status')->assertStatus(422);
    }

    public function test_plan_per_tenant_and_expiry_reminder(): void
    {
        $t = $this->newTenant('Usaha Pro', 'pro@contoh.test', ['expires_at' => now()->addDays(7)->toDateString(), 'max_devices' => 2]);

        $this->assertTrue(Tenancy::run($t, fn () => License::isPro()));
        $this->assertSame(2, Tenancy::run($t, fn () => License::limit('devices')));
        $this->assertSame('pro', Tenancy::run(Tenant::defaultId(), fn () => License::plan()));

        // pengingat H-7 dikirim dari nomor usaha utama
        $sent = [];
        Http::fake(function (Request $r) use (&$sent) {
            $sent[] = $r->data();

            return Http::response(['success' => true, 'id' => 'R'.count($sent)]);
        });
        config(['whatsapp.engine.url' => 'http://engine.test']);
        Tenancy::run(Tenant::defaultId(), fn () => Device::create(['name' => 'WA Pengelola', 'provider' => 'qr', 'is_active' => true, 'is_default' => true, 'status' => 'connected', 'credentials' => []]));
        Tenancy::forget();

        Artisan::call('tenants:remind');
        $this->assertCount(1, $sent);
        $this->assertSame('6281299990000', $sent[0]['to']);
        $this->assertStringContainsString('Usaha Pro', $sent[0]['message']);
        $this->assertStringContainsString('7 hari', $sent[0]['message']);
        $this->assertTrue(Message::withoutGlobalScopes()->where('tenant_id', Tenant::defaultId())->where('to', '6281299990000')->exists());

        Artisan::call('tenants:remind'); // tidak dikirim dua kali di hari yang sama
        $this->assertCount(1, $sent);

        // lewat masa tenggang -> Gratis
        $this->travel(11)->days();
        Tenancy::forget();
        $this->assertSame('free', Tenancy::run($t, fn () => License::plan()));
    }

    public function test_self_signup_when_enabled(): void
    {
        $this->get('/daftar')->assertNotFound();

        config(['license.signup_enabled' => true]);
        $this->get('/login')->assertSee('Daftar gratis');
        $this->post('/daftar', [
            'business' => 'Usaha Baru', 'name' => 'Nama Lengkap', 'phone' => '081277778888', 'email' => 'baru@contoh.test',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/dashboard');

        $tenant = Tenant::where('name', 'Usaha Baru')->first();
        $this->assertSame('free', $tenant->plan);
        $this->assertSame('6281277778888', $tenant->contact_phone);
        $this->assertAuthenticatedAs(User::withoutGlobalScopes()->where('email', 'baru@contoh.test')->first());
        $this->assertTrue(\App\Models\AppNotification::withoutGlobalScopes()->where('tenant_id', Tenant::defaultId())->where('type', 'tenant.signup')->exists());
    }
}
