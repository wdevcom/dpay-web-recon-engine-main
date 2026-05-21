<?php

namespace App\Recon\Parsers;

use Brick\Math\BigDecimal;
use DateTimeInterface;

/**
 * Canonical, parser-agnostic row of a financial event.
 *
 * Every parser (PKO CSV, SIBS, BLIK, PayU, MT940, internal importer) emits
 * a stream of these. The pipeline serialises them into source_rows.
 *
 *  - `rowType`: see SourceRow::TYPE_*
 *  - `raw`:    untouched original columns from the source file (for audit)
 *  - amounts in major units (PLN), positive for inflow, negative for outflow.
 *    For a sale: gross > 0, commission >= 0, net = gross - commission.
 *    For a refund: gross < 0.
 */
final class ParsedRow
{
    public function __construct(
        public readonly int $rowNo,
        public readonly string $rowType,
        public readonly DateTimeInterface $valueDate,
        public readonly ?DateTimeInterface $postedAt,
        public readonly BigDecimal $grossAmount,
        public readonly ?BigDecimal $netAmount,
        public readonly ?BigDecimal $commissionAmount,
        public readonly string $currency,
        public readonly ?string $externalId,
        public readonly array $raw,
        public readonly array $extra = [],
    ) {
    }

    public function toNormalizedArray(): array
    {
        return [
            'row_type'         => $this->rowType,
            'value_date'       => $this->valueDate->format('Y-m-d'),
            'posted_at'        => $this->postedAt?->format('Y-m-d'),
            'gross_amount'     => (string) $this->grossAmount,
            'net_amount'       => $this->netAmount ? (string) $this->netAmount : null,
            'commission_amount'=> $this->commissionAmount ? (string) $this->commissionAmount : null,
            'currency'         => $this->currency,
            'external_id'      => $this->externalId,
            'extra'            => $this->extra,
        ];
    }
}
