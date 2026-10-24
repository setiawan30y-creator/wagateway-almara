<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Timeline tiket. @2026-Alifiandra */
class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'user_id', 'type', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
