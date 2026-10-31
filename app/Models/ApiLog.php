<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log request API (tanpa body/secret). @2026-Alifiandra */
class ApiLog extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['api_key_id', 'method', 'path', 'status', 'duration_ms', 'ip', 'request_id'];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
