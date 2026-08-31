<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Konto księgi głównej. */
class Account extends Model
{
    public const TYPE_BANK = 'bank';
    public const TYPE_MASSCOLLECT = 'masscollect';
    public const TYPE_PAYABLE = 'payable';
    public const TYPE_SUSPENSE = 'suspense';
    public const TYPE_DISCREPANCY = 'discrepancy';
    public const TYPE_FEE = 'fee';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_EQUITY = 'equity';

    protected $fillable = [
        'code', 'name', 'type', 'currency', 'parent_id',
        'bank_account_id', 'masscollect_domain_id', 'tenant_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'bool'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(MasscollectDomain::class, 'masscollect_domain_id');
    }

    /**
     * WN minus MA na dzień. Filtrujemy po dacie KSIĘGOWANIA zapisu, a nie po
     * `created_at` wiersza - import wsteczny wstawia dziś operacje sprzed
     * tygodnia i saldo liczone po czasie wstawienia byłoby fikcją.
     */
    public function balance(?\DateTimeInterface $asOf = null): BigDecimal
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $this->id);

        if ($asOf !== null) {
            $query->whereDate('journal_entries.posted_at', '<=', $asOf);
        }

        $totals = $query->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as dr, COALESCE(SUM(journal_lines.credit), 0) as cr')->first();

        return BigDecimal::of((string) ($totals->dr ?? '0'))
            ->minus(BigDecimal::of((string) ($totals->cr ?? '0')))
            ->toScale(2, RoundingMode::HALF_UP);
    }
}
