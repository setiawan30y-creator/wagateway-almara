<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Basis pengetahuan untuk AI Agent (FAQ, produk, SOP, alamat, jam buka, dll). @2026-Alifiandra */
class KnowledgeItem extends Model
{
    use \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['title', 'content', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
