<?php

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojedyncza pozycja z wyciągu / historii. Kwota zawsze dodatnia, kierunek
 * niesie `direction` - tak jak robi to bank w camt (CdtDbtInd).
 */
class StatementEntry extends Model
{
    public const DIR_CREDIT = 'CRDT';
    public const DIR_DEBIT = 'DBIT';

    public const MATCH_UNMATCHED = 'unmatched';
    public const MATCH_MATCHED = 'matched';
    public const MATCH_SUSPENSE = 'suspense';
    public const MATCH_IGNORED = 'ignored';

    protected $fillable = [
        'bank_account_id', 'bank_statement_id', 'direction', 'amount_minor', 'currency',
        'booking_date', 'value_date', 'transaction_number', 'instruction_id',
        'end_to_end_id', 'transaction_id', 'counterparty_name', 'counterparty_account',
        'remittance_text', 'detected_virtual_account', 'virtual_account_id',
        'match_status', 'match_reason', 'journal_entry_id', 'fingerprint', 'raw',
    ];

    protected $casts = [
        'amount_minor'       => 'int',
        'booking_date'       => 'date',
        'value_date'         => 'date',
        'transaction_number' => 'int',
        'raw'                => 'array',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function virtualAccount(): BelongsTo
    {
        return $this->belongsTo(VirtualAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isCredit(): bool
    {
        return $this->direction === self::DIR_CREDIT;
    }

    public function amount(): BigDecimal
    {
        return Money::fromMinorUnits($this->amount_minor);
    }

    /** Kwota ze znakiem - do sum kontrolnych i raportów. */
    public function signedMinorUnits(): int
    {
        return $this->isCredit() ? $this->amount_minor : -$this->amount_minor;
    }
}
