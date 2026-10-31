<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan varian (A/B/C) per campaign — tidak harus semua varian dari template terpilih.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('variant_ids')->nullable()->after('template_ids');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn('variant_ids'));
    }
};
