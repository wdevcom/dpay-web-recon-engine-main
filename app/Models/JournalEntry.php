<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JournalEntry extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const SRC_BANK = 'bank_statement';
    public const SRC_SIBS = 'sibs_report';
    public const SRC_BLIK = 'blik_psp';
    public const SRC_PAYU = 'payu_report';
    public const SRC_PAYMENTERO = 'paymentero_report';
    public const SRC_INTERNAL_TXN = 'internal_txn';
    public const SRC_INTERNAL_PAYOUT = 'internal_payout';
    public const SRC_MANUAL = 'manual_adjustment';

    protected $fillable = [
        'entry_no', 'posted_at', 'value_date', 'description', 'source_type',
        'source_row_id', 'created_by', 'approved_by', 'approved_at',
        'status', 'reversed_by_entry_id', 'total', 'currency',
    ];

    protected $casts = [
        'posted_at' => 'date',
        'value_date' => 'date',
        'approved_at' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(SourceRow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
