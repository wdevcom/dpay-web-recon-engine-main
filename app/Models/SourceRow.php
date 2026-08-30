<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceRow extends Model
{
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_MANUAL = 'manual';
    public const STATUS_IGNORED = 'ignored';

    public const TYPE_SALE = 'sale';
    public const TYPE_REFUND = 'refund';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_FEE = 'fee';
    public const TYPE_BANK_CREDIT = 'bank_credit';
    public const TYPE_BANK_DEBIT = 'bank_debit';
    public const TYPE_INTERNAL_TXN = 'internal_txn';

    protected $fillable = [
        'source_document_id', 'row_no', 'raw_payload', 'normalized',
        'external_id', 'value_date', 'posted_at',
        'gross_amount', 'net_amount', 'commission_amount',
        'currency', 'row_type', 'match_status', 'journal_entry_id',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'normalized' => 'array',
        'value_date' => 'date',
        'posted_at' => 'date',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
