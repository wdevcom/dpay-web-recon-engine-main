<?php

namespace App\Models;

use App\Banking\Masscollect\AccountNumberMask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domena przestrzeni numerów masscollect. Cyfra domeny jest zapisana wprost
 * w numerze rachunku, więc podział jest widoczny na wyciągu i odtwarzalny
 * bez bazy.
 */
class MasscollectDomain extends Model
{
    public const LIFECYCLE_ONE_TIME = 'one_time';
    public const LIFECYCLE_PERSISTENT = 'persistent';

    protected $fillable = [
        'code', 'digits', 'name', 'lifecycle', 'tenant_id', 'bank_account_id',
        'next_sequence', 'allocated_count', 'default_ttl_minutes', 'is_active',
    ];

    protected $casts = [
        'next_sequence'   => 'int',
        'allocated_count' => 'int',
        'is_active'       => 'bool',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(VirtualAccount::class);
    }

    public function ledgerAccount(): ?Account
    {
        return Account::where('masscollect_domain_id', $this->id)->first();
    }

    public function capacity(): int
    {
        return AccountNumberMask::fromConfig()->capacityPerDomain();
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->capacity() - $this->next_sequence + 1);
    }

    public function isRunningLow(): bool
    {
        return $this->remainingCapacity() <= (int) config('bnp.masscollect.low_watermark', 1000);
    }
}
