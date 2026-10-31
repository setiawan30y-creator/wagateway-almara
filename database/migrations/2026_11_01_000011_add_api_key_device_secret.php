<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token API per perangkat (gaya Fonnte): key bisa terikat ke satu perangkat
 * dan disimpan terenkripsi (APP_KEY) agar bisa ditampilkan & disalin kapan saja.
 * Key lama (sebelum versi ini) hanya punya hash — tidak bisa ditampilkan ulang.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('secret')->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn('secret');
        });
    }
};
