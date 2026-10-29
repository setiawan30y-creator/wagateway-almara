<?php

namespace App\Jobs;

use App\AI\AiAgent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membuat balasan AI untuk satu pesan masuk (dijalankan di luar request webhook).
 * @2026-Alifiandra
 */
class GenerateAiReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public int $conversationId, public int $messageId) {}

    public function handle(AiAgent $agent): void
    {
        $conversation = Conversation::find($this->conversationId);
        $message = Message::find($this->messageId);

        if (! $conversation || ! $message) {
            return;
        }

        // Bila sudah ada pesan masuk yang lebih baru, biarkan job berikutnya yang menjawab (hindari balasan ganda)
        $newer = Message::where('conversation_id', $conversation->id)->where('direction', 'in')->where('id', '>', $message->id)->exists();
        // Bila sudah ada balasan manusia/otomatis setelah pesan ini, jangan menjawab lagi
        $answered = Message::where('conversation_id', $conversation->id)->where('direction', 'out')->where('id', '>', $message->id)->exists();

        if ($newer || $answered) {
            return;
        }

        try {
            $agent->handleIncoming($conversation, $message);
        } catch (Throwable $e) {
            Log::warning('[AI] gagal membalas', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }
}
