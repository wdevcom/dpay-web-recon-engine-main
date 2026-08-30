<?php

namespace App\Recon\Ledger;

use Brick\Math\BigDecimal;
use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public function __construct(string $debits, string $credits, string $description)
    {
        $diff = BigDecimal::of($debits)->minus(BigDecimal::of($credits));
        parent::__construct(sprintf(
            'Unbalanced journal entry "%s": DR=%s, CR=%s (diff=%s).',
            $description,
            $debits,
            $credits,
            (string) $diff
        ));
    }
}
