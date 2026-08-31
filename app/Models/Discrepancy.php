<?php

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigDecimal;
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

    public const TYPE_UNIDENTIFIED_PAYMENT = 'unidentified_payment';
    public const TYPE_AMOUNT_MISMATCH = 'amount_mismatch';
    public const TYPE_MISSING_IN_BANK = 'missing_in_bank';
    public const TYPE_MISSING_AT_CONSUMER = 'missing_at_consumer';
    public const TYPE_DUPLICATE = 'duplicate';
    public const TYPE_PAYMENT_AFTER_EXPIRY = 'payment_after_expiry';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'reconciliation_id', 'statement_entry_id', 'virtual_account_id', 'tenant_id',
        'type', 'amount_minor', 'currency', 'description', 'proposed_resolution',
        'status', 'severity', 'assignee_id', 'reviewer_id', 'reviewed_at',
        'resolution_journal_entry_id',
    ];

    protected $casts = [
        'amount_minor' => 'int',
        'reviewed_at'  => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(StatementEntry::class);
    }

    public function virtualAccount(): BelongsTo
    {
        return $this->belongsTo(VirtualAccount::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function amount(): BigDecimal
    {
        return Money::fromMinorUnits($this->amount_minor);
    }
}
