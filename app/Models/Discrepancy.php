<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Discrepancy extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WRITTEN_OFF = 'written_off';

    public const TYPE_OVER = 'over';
    public const TYPE_SHORT = 'short';
    public const TYPE_DUPLICATE = 'duplicate';
    public const TYPE_MISSING = 'missing';
    public const TYPE_WRONG_AMOUNT = 'wrong_amount';
    public const TYPE_UNEXPECTED_FEE = 'unexpected_fee';
    public const TYPE_REFUND_OUTSIDE_SYSTEM = 'refund_outside_system';
    public const TYPE_COMMISSION_SETTLEMENT = 'commission_settlement';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'reconciliation_id', 'provider_id', 'type', 'amount', 'currency',
        'description', 'proposed_resolution', 'status', 'severity',
        'assignee_id', 'reviewer_id', 'reviewed_at',
        'resolution_journal_entry_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function resolutionEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'resolution_journal_entry_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
