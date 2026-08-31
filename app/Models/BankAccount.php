<?php

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rachunek rzeczywisty w BNP Paribas. */
class BankAccount extends Model
{
    public const PURPOSE_OPERATIONAL = 'operational';
    public const PURPOSE_MASSCOLLECT = 'masscollect';
    public const PURPOSE_SETTLEMENT = 'settlement';

    protected $fillable = [
        'iban', 'name', 'currency', 'purpose', 'is_active',
        'available_balance_minor', 'booked_balance_minor', 'balance_synced_at', 'sync_error',
        'last_transaction_number', 'last_transaction_date', 'history_synced_at',
    ];

    protected $casts = [
        'is_active'               => 'bool',
        'available_balance_minor' => 'int',
        'booked_balance_minor'    => 'int',
        'balance_synced_at'       => 'datetime',
        'last_transaction_date'   => 'date',
        'history_synced_at'       => 'datetime',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(MasscollectDomain::class);
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(StatementEntry::class);
    }

    public function ledgerAccount(): ?Account
    {
        return Account::where('bank_account_id', $this->id)->where('type', Account::TYPE_BANK)->first();
    }

    public function availableBalance(): ?BigDecimal
    {
        return $this->available_balance_minor === null ? null : Money::fromMinorUnits($this->available_balance_minor);
    }

    public function bookedBalance(): ?BigDecimal
    {
        return $this->booked_balance_minor === null ? null : Money::fromMinorUnits($this->booked_balance_minor);
    }
}
