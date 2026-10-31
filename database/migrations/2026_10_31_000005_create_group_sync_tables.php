<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.6: grup WhatsApp, chat grup di Inbox, sinkron kontak & riwayat, broadcast dengan lampiran.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('jid', 64);
            $table->string('subject', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('participants_count')->default(0);
            $table->json('participants')->nullable(); // [{phone, admin}]
            $table->boolean('is_admin')->default(false);
            $table->boolean('announce')->default(false); // hanya admin yang boleh kirim
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'jid']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->change();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->unique()->after('contact_id')->constrained('wa_groups')->cascadeOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('to', 64)->change();
            $table->foreignId('group_id')->nullable()->after('conversation_id')->constrained('wa_groups')->nullOnDelete();
            $table->string('participant', 30)->nullable()->after('group_id');
            $table->string('participant_name', 150)->nullable()->after('participant');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('template_id');
            $table->string('media_path', 255)->nullable()->after('media_type');
            $table->string('media_name', 191)->nullable()->after('media_path');
            $table->string('media_mime', 100)->nullable()->after('media_name');
            $table->unsignedInteger('media_size')->nullable()->after('media_mime');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['media_type', 'media_path', 'media_name', 'media_mime', 'media_size']));

        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn(['participant', 'participant_name']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
