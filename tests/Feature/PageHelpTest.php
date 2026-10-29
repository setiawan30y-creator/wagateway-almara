<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PageHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tombol bantuan di setiap halaman. @2026-Alifiandra */
class PageHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_has_help_button_with_page_specific_content(): void
    {
        $admin = User::factory()->create(['role' => 'owner']);

        $pages = [
            '/dashboard' => 'Dashboard', '/inbox' => 'Inbox', '/kontak' => 'Kontak &amp; CRM', '/kontak/tambah' => 'Detail / Form Kontak',
            '/tiket' => 'Tiket (Helpdesk)', '/kirim' => 'Kirim Pesan', '/pesan' => 'Riwayat Pesan', '/broadcast' => 'Broadcast',
            '/template' => 'Template', '/smart-link' => 'Smart Link', '/auto-reply' => 'Auto-Reply', '/ai-agent' => 'AI Agent',
            '/perangkat' => 'Perangkat', '/api-key' => 'API Key', '/webhook' => 'Webhook', '/pengguna' => 'Pengguna &amp; Role',
            '/log' => 'Log', '/pengaturan' => 'Pengaturan', '/notifikasi' => 'Notifikasi', '/akun' => 'Akun', '/panduan' => 'Panduan',
        ];

        foreach ($pages as $url => $title) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('data-help-open', false)
                ->assertSee('<h2 id="help-title">'.$title.'</h2>', false)
                ->assertSee('Cara pakai');
        }
    }

    public function test_resolver_prefers_exact_then_wildcard_then_default(): void
    {
        $this->assertSame('Kontak & CRM', PageHelp::for('contacts.index')['judul']);
        $this->assertSame('Detail / Form Kontak', PageHelp::for('contacts.show')['judul']);
        $this->assertSame('Scan QR', PageHelp::for('devices.pair')['judul']);
        $this->assertSame('Perangkat', PageHelp::for('devices.index')['judul']);
        $this->assertSame('Bantuan', PageHelp::for('tidak.ada')['judul']);
        $this->assertSame('api', PageHelp::for('api-keys.index')['doc']);
    }
}
