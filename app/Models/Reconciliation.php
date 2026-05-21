<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reconciliation extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_BALANCED = 'balanced';
    public const STATUS_WITH_DISCREPANCY = 'with_discrepancy';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'group_no', 'provider_id', 'period_from', 'period_to',
        'expected_total', 'actual_total', 'difference', 'currency',
        'status', 'created_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'expected_total' => 'decimal:2',
        'actual_total' => 'decimal:2',
        'difference' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function sourceRows(): BelongsToMany
    {
        return $this->belongsToMany(SourceRow::class, 'reconciliation_items')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }
}
