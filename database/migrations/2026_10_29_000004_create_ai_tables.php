<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Agent: knowledge base, log pemakaian, penanda pesan AI & kontrol AI per percakapan.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->text('content');
            $table->string('category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20)->default('reply'); // reply | suggest | test
            $table->string('model', 150)->nullable();
            $table->string('status', 20); // ok | handoff | error | skipped
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('sent_by_ai')->default(false)->after('direction');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(true)->after('assigned_to');
            $table->timestamp('ai_paused_until')->nullable()->after('ai_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $t) => $t->dropColumn(['ai_enabled', 'ai_paused_until']));
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn('sent_by_ai'));
        Schema::dropIfExists('ai_logs');
        Schema::dropIfExists('knowledge_items');
    }
};
