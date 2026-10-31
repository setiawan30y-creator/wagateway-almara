<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\RecipientList;
use App\Models\Template;
use App\Models\User;
use App\Models\WaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** v0.7: lampiran template, multi-template acak + spintax, rombongan, duplikat campaign. @2026-Alifiandra */
class BroadcastRotationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'owner']);
        $this->device = Device::create(['name' => 'Sim', 'provider' => 'log', 'is_active' => true, 'is_default' => true]);
    }

    public function test_spintax_renders_one_option(): void
    {
        $seen = [];
        for ($i = 0; $i < 40; $i++) {
            $out = Template::render('{Halo|Hai} {{first_name}}, {promo {hari ini|minggu ini}|ada diskon}', new Contact(['name' => 'Budi Santoso']));
            $this->assertMatchesRegularExpression('/^(Halo|Hai) Budi, (promo (hari ini|minggu ini)|ada diskon)$/', $out);
            $seen[$out] = true;
        }
        $this->assertGreaterThan(1, count($seen));
    }

    public function test_template_attachment_upload_and_remove(): void
    {
        $this->actingAs($this->admin)->post('/template', [
            'name' => 'Katalog', 'category' => 'marketing', 'is_active' => 1, 'variants' => ['A' => 'Halo {{name}}'],
            'file' => UploadedFile::fake()->image('katalog.jpg', 300, 300),
        ])->assertRedirect();

        $t = Template::first();
        $this->assertSame('image', $t->media_type);
        $this->actingAs($this->admin)->get('/template')->assertOk()->assertSee('📎');
        $this->actingAs($this->admin)->get("/template/{$t->id}/ubah")->assertOk()->assertSee('katalog.jpg');
        $this->actingAs($this->admin)->get("/template/{$t->id}/lampiran")->assertOk();

        $this->actingAs($this->admin)->put("/template/{$t->id}", ['name' => 'Katalog', 'category' => 'marketing', 'variants' => ['A' => 'Halo'], 'remove_media' => 1])->assertRedirect();
        $this->assertNull($t->refresh()->media_type);
    }

    public function test_multi_template_rotation_with_template_media_and_rombongan(): void
    {
        $t1 = Template::create(['name' => 'Promo foto', 'category' => 'marketing', 'is_active' => true, 'media_type' => 'image', 'media_path' => 'wa-media/x.jpg', 'media_name' => 'x.jpg', 'media_mime' => 'image/jpeg', 'media_size' => 10]);
        $t1->variants()->create(['label' => 'A', 'body' => 'T1A {{name}}']);
        $t1->variants()->create(['label' => 'B', 'body' => 'T1B {{name}}']);
        $t2 = Template::create(['name' => 'Promo teks', 'category' => 'marketing', 'is_active' => true]);
        $t2->variants()->create(['label' => 'A', 'body' => 'T2A {{name}}']);

        $list = RecipientList::create(['name' => 'Reseller']);
        $members = collect(range(1, 6))->map(fn ($i) => Contact::create(['name' => "R{$i}", 'phone' => '6281300000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'consent' => 'opted_in']));
        $list->addContacts($members->pluck('id'));
        Contact::create(['name' => 'Bukan anggota', 'phone' => '6281399999999', 'consent' => 'opted_in']);

        $this->actingAs($this->admin)->postJson('/broadcast/pratinjau', ['lists' => [$list->id]])->assertJson(['eligible' => 6]);

        $this->actingAs($this->admin)->post('/broadcast', [
            'name' => 'Rotasi', 'device_id' => $this->device->id, 'template_ids' => [$t1->id, $t2->id],
            'lists' => [$list->id], 'rate_per_minute' => 60, 'mode' => 'now',
        ])->assertRedirect();

        $campaign = Campaign::first();
        $this->assertSame([$t1->id, $t2->id], $campaign->templateIds());
        $messages = Message::where('campaign_id', $campaign->id)->get();
        $this->assertCount(6, $messages);
        $this->assertFalse($messages->contains('to', '6281399999999'));

        // 3 varian x 6 penerima -> tiap varian dipakai 2x (merata, urutan acak)
        $byPrefix = $messages->groupBy(fn ($m) => substr($m->body, 0, 3))->map->count();
        $this->assertEquals(['T1A' => 2, 'T1B' => 2, 'T2A' => 2], $byPrefix->sortKeys()->all());
        // lampiran template ikut hanya untuk pesan dari template berlampiran
        $this->assertTrue($messages->filter(fn ($m) => str_starts_with($m->body, 'T1'))->every(fn ($m) => $m->media_type === 'image'));
        $this->assertTrue($messages->filter(fn ($m) => str_starts_with($m->body, 'T2'))->every(fn ($m) => $m->media_type === null));

        $this->actingAs($this->admin)->get("/broadcast/{$campaign->id}")->assertOk()->assertSee('Promo foto, Promo teks')->assertSee('Rombongan: Reseller');

        // duplikat untuk rombongan lain
        $this->actingAs($this->admin)->get("/broadcast/buat?dari={$campaign->id}")->assertOk()->assertSee('Rotasi (salinan)')->assertSee('Menyalin campaign');
    }

    public function test_rombongan_members_from_numbers_group_and_bulk(): void
    {
        $this->actingAs($this->admin)->post('/rombongan', ['name' => 'Peserta Event'])->assertRedirect();
        $list = RecipientList::first();

        $this->actingAs($this->admin)->post("/rombongan/{$list->id}/anggota", ['source' => 'numbers', 'numbers' => "081234500001\n+62 812-3450-0002, 123", 'consent' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, $list->contacts()->count());
        $this->assertSame(2, $list->contacts()->where('consent', 'opted_in')->count());

        $group = WaGroup::create(['device_id' => $this->device->id, 'jid' => '1203630@g.us', 'subject' => 'Grup', 'participants' => [['phone' => '6281234500001', 'admin' => null], ['phone' => '6281234500003', 'admin' => null]]]);
        $this->actingAs($this->admin)->post("/rombongan/{$list->id}/anggota", ['source' => 'group', 'group_id' => $group->id])->assertRedirect();
        $this->assertSame(3, $list->contacts()->count()); // tanpa duplikat
        $this->assertSame('unknown', Contact::where('phone', '6281234500003')->value('consent'));

        $other = Contact::create(['phone' => '6281299990000', 'name' => 'Lain']);
        $this->actingAs($this->admin)->post('/kontak/massal', ['ids' => [$other->id], 'action' => 'list', 'value' => 'Peserta Event'])->assertRedirect();
        $this->assertSame(4, $list->contacts()->count());

        $this->actingAs($this->admin)->get('/rombongan')->assertOk()->assertSee('Peserta Event');
        $this->actingAs($this->admin)->get("/rombongan/{$list->id}")->assertOk()->assertSee('+6281299990000');

        $this->actingAs($this->admin)->post("/rombongan/{$list->id}/keluarkan", ['ids' => [$other->id]])->assertRedirect();
        $this->assertSame(3, $list->contacts()->count());

        $this->actingAs($this->admin)->get("/broadcast/buat?rombongan={$list->id}")->assertOk()->assertSee('value="'.$list->id.'" checked', false);
    }

    public function test_paste_numbers_with_names(): void
    {
        $list = RecipientList::create(['name' => 'Pelanggan']);
        Contact::create(['phone' => '6281234500009', 'name' => 'Nama Lama']);

        $this->actingAs($this->admin)->post("/rombongan/{$list->id}/anggota", ['source' => 'numbers', 'consent' => 1,
            'numbers' => "081234500001, Budi Santoso\nSiti Aminah - 0812 3450 0002\n6281234500003\tAndi\n081234500009, Nama Baru"])->assertRedirect();

        $this->assertSame(4, $list->contacts()->count());
        $this->assertSame('Budi Santoso', Contact::where('phone', '6281234500001')->value('name'));
        $this->assertSame('Siti Aminah', Contact::where('phone', '6281234500002')->value('name'));
        $this->assertSame('Andi', Contact::where('phone', '6281234500003')->value('name'));
        $this->assertSame('Nama Lama', Contact::where('phone', '6281234500009')->value('name')); // tidak ditimpa
        $this->assertSame('Halo Budi', Template::render('Halo {{first_name}}', Contact::where('phone', '6281234500001')->first()));
    }
}
