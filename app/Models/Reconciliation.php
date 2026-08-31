<?php

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zestawienie naszej księgi z danymi konsumenta za okres. */
class Reconciliation extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_BALANCED = 'balanced';
    public const STATUS_WITH_DISCREPANCY = 'with_discrepancy';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'group_no', 'tenant_id', 'bank_account_id', 'masscollect_domain_id',
        'period_from', 'period_to', 'currency',
        'bank_total_minor', 'counterparty_total_minor', 'difference_minor',
        'matched_count', 'unmatched_count',
        'status', 'created_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'period_from'              => 'date',
        'period_to'                => 'date',
        'bank_total_minor'         => 'int',
        'counterparty_total_minor' => 'int',
        'difference_minor'         => 'int',
        'closed_at'                => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(MasscollectDomain::class, 'masscollect_domain_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }

    public function difference(): BigDecimal
    {
        return Money::fromMinorUnits($this->difference_minor);
    }
}
