<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class JournalEntry extends Model
{
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const SRC_BANK_STATEMENT = 'bank_statement';
    public const SRC_MASSCOLLECT = 'masscollect_payment';
    public const SRC_PAYOUT = 'payout';
    public const SRC_FEE = 'fee';
    public const SRC_SETTLEMENT = 'settlement';
    public const SRC_MANUAL = 'manual_adjustment';
    public const SRC_REVERSAL = 'reversal';

    protected $fillable = [
        'entry_no', 'posted_at', 'value_date', 'description', 'source_type',
        'statement_entry_id', 'created_by', 'status', 'reversed_by_entry_id',
        'total', 'currency',
    ];

    protected $casts = [
        'posted_at'  => 'date',
        'value_date' => 'date',
        'total'      => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(StatementEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
