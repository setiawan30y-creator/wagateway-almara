<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.8: tanggal perangkat pertama kali tertaut (untuk pemanasan nomor / warm-up).
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('paired_at')->nullable()->after('last_seen_at');
        });

        // perangkat lama yang sudah tersambung dianggap tertaut sejak dibuat
        DB::table('devices')->whereNull('paired_at')->where('status', 'connected')->update(['paired_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn('paired_at'));
    }
};
