<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log pemakaian AI (tanpa isi percakapan). @2026-Alifiandra */
class AiLog extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'purpose', 'model', 'status', 'prompt_tokens', 'completion_tokens', 'latency_ms', 'error'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
