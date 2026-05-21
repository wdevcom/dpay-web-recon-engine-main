<?php

namespace App\Recon\Ledger;

use App\Models\Account;
use Brick\Math\BigDecimal;

/**
 * One side of a journal entry while it is still being built (before persistence).
 * Use Posting::debit() / Posting::credit() to construct.
 */
final class PostingLine
{
    public function __construct(
        public readonly Account $account,
        public readonly BigDecimal $debit,
        public readonly BigDecimal $credit,
        public readonly string $currency,
        public readonly ?string $memo = null,
        public readonly ?string $externalRef = null,
    ) {
    }
}
