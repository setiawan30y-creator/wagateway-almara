<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu pesan benar-benar dikirim ke WhatsApp (untuk progres broadcast realtime).
 * Pesan broadcast Scan QR antre dulu di engine (jeda acak) sebelum terkirim.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('wa_sent_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn('wa_sent_at'));
    }
};
