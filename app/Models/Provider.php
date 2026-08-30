<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $fillable = [
        'code', 'name', 'default_currency',
        'match_window_days', 'amount_tolerance',
        'clearing_account_id', 'commission_account_id', 'bank_account_id',
        'sftp_config', 'is_active',
    ];

    protected $casts = [
        'sftp_config' => 'encrypted:array',
        'is_active' => 'bool',
        'amount_tolerance' => 'decimal:2',
    ];

    public function clearingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'clearing_account_id');
    }

    public function commissionAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'commission_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function sourceDocuments(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }
}
