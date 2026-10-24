<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan internal percakapan (tidak dikirim ke customer). @2026-Alifiandra */
class ConversationNote extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
