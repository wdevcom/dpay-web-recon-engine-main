<?php

namespace App\Ledger;

use Brick\Math\BigDecimal;
use RuntimeException;

class UnbalancedEntryException extends RuntimeException
{
    public function __construct(string $debits, string $credits, string $description)
    {
        $diff = BigDecimal::of($debits)->minus(BigDecimal::of($credits));
        parent::__construct(sprintf(
            'Niezbilansowany zapis "%s": WN=%s, MA=%s (różnica=%s).',
            $description,
            $debits,
            $credits,
            (string) $diff
        ));
    }
}
