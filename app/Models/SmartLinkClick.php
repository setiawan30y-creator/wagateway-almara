<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Klik smart link (tanpa menyimpan IP asli). @2026-Alifiandra */
class SmartLinkClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['smart_link_id', 'visitor', 'referer', 'device'];
}
