<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.7: lampiran di template, broadcast multi-template (rotasi acak), rombongan penerima.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('category');
            $table->string('media_path', 255)->nullable()->after('media_type');
            $table->string('media_name', 191)->nullable()->after('media_path');
            $table->string('media_mime', 100)->nullable()->after('media_name');
            $table->unsignedInteger('media_size')->nullable()->after('media_mime');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('template_ids')->nullable()->after('template_id');
        });

        Schema::create('recipient_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contact_recipient_list', function (Blueprint $table) {
            $table->foreignId('recipient_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['recipient_list_id', 'contact_id']);
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_recipient_list');
        Schema::dropIfExists('recipient_lists');
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn('template_ids'));
        Schema::table('templates', fn (Blueprint $t) => $t->dropColumn(['media_type', 'media_path', 'media_name', 'media_mime', 'media_size']));
    }
};
