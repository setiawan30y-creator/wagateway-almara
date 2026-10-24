<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat persetujuan (consent history). @2026-Alifiandra */
class ConsentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['contact_id', 'consent', 'source', 'note', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
