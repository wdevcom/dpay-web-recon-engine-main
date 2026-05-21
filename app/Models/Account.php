<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const TYPE_BANK = 'bank';
    public const TYPE_CLEARING = 'clearing';
    public const TYPE_MERCHANT = 'merchant';
    public const TYPE_COMMISSION = 'commission';
    public const TYPE_SUSPENSE = 'suspense';
    public const TYPE_DISCREPANCY = 'discrepancy';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_EQUITY = 'equity';

    protected $fillable = [
        'code', 'name', 'type', 'currency',
        'parent_id', 'provider_id', 'external_ref', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Returns DR - CR for this account, as BigDecimal.
     * Positive = debit balance; for accounts whose normal balance is on the
     * credit side (revenue, equity), the natural balance is negative here.
     */
    public function balance(?\DateTimeInterface $asOf = null): BigDecimal
    {
        $query = $this->lines();
        if ($asOf !== null) {
            $query = $query->where('created_at', '<=', $asOf);
        }
        $totals = $query->selectRaw('COALESCE(SUM(debit), 0) as dr, COALESCE(SUM(credit), 0) as cr')->first();

        $dr = BigDecimal::of($totals->dr ?? '0');
        $cr = BigDecimal::of($totals->cr ?? '0');

        return $dr->minus($cr)->toScale(2, RoundingMode::HALF_UP);
    }
}
