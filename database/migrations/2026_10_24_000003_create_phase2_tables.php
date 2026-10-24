<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2: pesan media, ticketing, smart link, auto-reply.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('body'); // image | video | audio | document
            $table->string('media_path', 255)->nullable()->after('media_type');
            $table->string('media_name', 191)->nullable()->after('media_path');
            $table->string('media_mime', 100)->nullable()->after('media_name');
            $table->unsignedInteger('media_size')->nullable()->after('media_mime');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 191);
            $table->text('description')->nullable();
            $table->string('category', 30)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open')->index(); // open | pending | resolved | closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30); // created | status | priority | assigned | note | escalated | notified
            $table->text('body')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('smart_links', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 60)->unique();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable(); // bila kosong pakai nomor perangkat
            $table->text('message')->nullable();
            $table->foreignId('tag_id')->nullable()->constrained()->nullOnDelete();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('chats_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('smart_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_link_id')->constrained()->cascadeOnDelete();
            $table->string('visitor', 64)->nullable(); // hash ip+ua, bukan IP asli
            $table->string('referer', 255)->nullable();
            $table->string('device', 20)->nullable(); // mobile | desktop
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('smart_link_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });

        Schema::create('auto_replies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('trigger', 20)->default('keyword'); // keyword | welcome | away
            $table->string('match_type', 20)->default('contains'); // exact | contains | starts_with
            $table->string('keywords', 500)->nullable();
            $table->text('reply');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete(); // null = semua perangkat
            $table->foreignId('add_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedSmallInteger('cooldown_minutes')->default(60);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auto_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_reply_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['auto_reply_id', 'contact_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_reply_logs');
        Schema::dropIfExists('auto_replies');
        Schema::table('contacts', fn (Blueprint $t) => $t->dropConstrainedForeignId('smart_link_id'));
        Schema::dropIfExists('smart_link_clicks');
        Schema::dropIfExists('smart_links');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn(['media_type', 'media_path', 'media_name', 'media_mime', 'media_size']));
    }
};
