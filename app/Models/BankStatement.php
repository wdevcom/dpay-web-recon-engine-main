<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jedno pobranie danych z banku: historia, przyrost albo wyciąg. */
class BankStatement extends Model
{
    public const SOURCE_HISTORY = 'history';
    public const SOURCE_INCREMENTAL = 'incremental';
    public const SOURCE_STATEMENT = 'statement';
    public const SOURCE_MBR_STATEMENT = 'mbr_statement';

    protected $fillable = [
        'bank_account_id', 'source', 'message_id', 'period_from', 'period_to',
        'entries_count', 'new_entries_count', 'document_namespace', 'fetched_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'fetched_at'  => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StatementEntry::class);
    }
}
