<?php

namespace App\Ledger;

use App\Models\Account;
use Brick\Math\BigDecimal;

/** Jedna strona zapisu, zanim trafi do bazy. Budowana przez Posting. */
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
