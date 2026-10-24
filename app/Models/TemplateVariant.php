<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @2026-Alifiandra */
class TemplateVariant extends Model
{
    protected $fillable = ['template_id', 'label', 'body'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
