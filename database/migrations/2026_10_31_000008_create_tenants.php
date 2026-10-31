<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.0: multi-tenant (satu server untuk banyak penyewa).
 * Semua data lama dipindahkan ke penyewa #1 ("Usaha Utama") milik pemilik aplikasi.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    /** Tabel yang datanya terpisah per penyewa. */
    public const TABLES = [
        'users', 'devices', 'api_keys', 'api_logs', 'messages', 'audit_logs', 'contacts', 'tags', 'consent_events',
        'conversations', 'templates', 'campaigns', 'webhooks', 'app_notifications', 'tickets', 'smart_links',
        'auto_replies', 'knowledge_items', 'ai_logs', 'wa_groups', 'recipient_lists',
    ];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('plan', 20)->default('free');          // free | pro
            $table->unsignedInteger('max_devices')->nullable();     // null = ikut paket
            $table->unsignedInteger('daily_messages')->nullable();  // null = ikut paket
            $table->date('expires_at')->nullable();                 // null = tanpa batas waktu
            $table->string('status', 20)->default('active');        // active | suspended
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();        // untuk pengingat perpanjangan
            $table->string('license_id', 40)->nullable()->index();  // kode lisensi yang dipakai
            $table->string('last_reminder', 20)->nullable();        // mis. "2026-11-30:h7"
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedInteger('days')->default(0);
            $table->string('plan', 20)->nullable();
            $table->date('period_until')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Usaha Utama', 'plan' => 'pro', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
            DB::table($t)->update(['tenant_id' => $tenantId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false)->after('role');
        });
        // pemilik pertama menjadi super-admin (pengelola semua penyewa)
        $owner = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id') ?? DB::table('users')->orderBy('id')->value('id');
        if ($owner) {
            DB::table('users')->where('id', $owner)->update(['is_superadmin' => true]);
        }

        // nomor & nama tag unik per penyewa (bukan global)
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->unique(['tenant_id', 'phone']);
        });
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['tenant_id', 'name']);
        });

        // pengaturan per penyewa
        Schema::create('settings_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });
        foreach (DB::table('settings')->get() as $row) {
            DB::table('settings_tmp')->insert(['tenant_id' => $tenantId, 'key' => $row->key, 'value' => $row->value, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);
        }
        Schema::drop('settings');
        Schema::rename('settings_tmp', 'settings');
    }

    public function down(): void
    {
        // Tidak disediakan: pemisahan data per penyewa tidak bisa dibatalkan otomatis dengan aman.
    }
};
