<?php

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mikro rachunek wydany konsumentowi na konkretny byt po jego stronie. */
class VirtualAccount extends Model
{
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'iban', 'nrb', 'masscollect_domain_id', 'sequence', 'tenant_id',
        'owner_type', 'owner_ref', 'label', 'lifecycle', 'status',
        'expected_amount_minor', 'currency', 'received_amount_minor', 'payments_count',
        'expires_at', 'first_payment_at', 'released_at', 'metadata',
    ];

    protected $casts = [
        'sequence'              => 'int',
        'expected_amount_minor' => 'int',
        'received_amount_minor' => 'int',
        'payments_count'        => 'int',
        'expires_at'            => 'datetime',
        'first_payment_at'      => 'datetime',
        'released_at'           => 'datetime',
        'metadata'              => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(MasscollectDomain::class, 'masscollect_domain_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StatementEntry::class);
    }

    public function expectedAmount(): ?BigDecimal
    {
        return $this->expected_amount_minor === null ? null : Money::fromMinorUnits($this->expected_amount_minor);
    }

    public function receivedAmount(): BigDecimal
    {
        return Money::fromMinorUnits($this->received_amount_minor);
    }

    public function isOpenForPayments(): bool
    {
        return in_array($this->status, [self::STATUS_ALLOCATED, self::STATUS_ACTIVE], true)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
