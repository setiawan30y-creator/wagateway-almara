<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan pembayaran sewa. @2026-Alifiandra */
class TenantPayment extends Model
{
    protected $fillable = ['tenant_id', 'amount', 'days', 'plan', 'period_until', 'note', 'recorded_by', 'paid_at'];

    protected function casts(): array
    {
        return ['period_until' => 'date', 'paid_at' => 'datetime', 'amount' => 'integer', 'days' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withoutGlobalScopes();
    }
}
