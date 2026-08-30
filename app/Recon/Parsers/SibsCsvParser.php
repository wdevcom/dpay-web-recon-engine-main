<?php

namespace App\Recon\Parsers;

use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Support\Money;
use Carbon\CarbonImmutable;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * SIBS Reconciliation CSV (semicolon-separated).
 *
 * Header columns:
 *   MERCHANT_ID;SPONSORED_MERCHANT_ID;TERMINAL_TYPE;TRANSACTION_DATE;ACQUIRER_ID;
 *   TRANSACTION_ID;BRAND;TRANSACTION_GROSS_AMOUNT;CURRENCY;COMMISSION_PCT;
 *   COMMISSION_FXD;COMMISSION_AMT;TRANSACTION_NET_AMOUNT;PAYOUT_ACCOUNT;
 *   PAYOUT_AMOUNT;PAYOUT_DATE;TRANSACTION_TYPE;ORIGINAL_TRANSACTION_ID;
 *   ORIGINAL_TRANSACTION_DATE;REFUND_ACCOUNT_NUMBER
 *
 * CURRENCY is the ISO numeric (985 = PLN). We map known codes; default 'PLN'.
 *
 * row_type:
 *   - SALES  -> TYPE_SALE   (positive gross)
 *   - REFUND -> TYPE_REFUND (negative gross)
 */
class SibsCsvParser implements StatementParser
{
    private const ISO_NUMERIC_TO_ALPHA = [
        '985' => 'PLN',
        '978' => 'EUR',
        '840' => 'USD',
    ];

    public function format(): string
    {
        return 'sibs_csv';
    }

    public function supports(SourceDocument $doc, string $absolutePath): bool
    {
        if ($doc->format === $this->format()) {
            return true;
        }
        $head = file_get_contents($absolutePath, false, null, 0, 256) ?: '';
        return str_starts_with($head, 'MERCHANT_ID;SPONSORED_MERCHANT_ID;TERMINAL_TYPE;TRANSACTION_DATE');
    }

    public function parse(SourceDocument $doc, string $absolutePath): iterable
    {
        $csv = Reader::createFromPath($absolutePath, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $rowNo = 0;
        foreach (Statement::create()->process($csv) as $record) {
            $rowNo++;
            if (empty($record['TRANSACTION_ID'])) {
                continue;
            }

            $isRefund = strtoupper(trim($record['TRANSACTION_TYPE'])) === 'REFUND';
            $gross = Money::of($record['TRANSACTION_GROSS_AMOUNT']);
            $net   = Money::of($record['TRANSACTION_NET_AMOUNT']);
            $fee   = Money::of($record['COMMISSION_AMT']);
            $payout = Money::of($record['PAYOUT_AMOUNT']);

            $currencyAlpha = self::ISO_NUMERIC_TO_ALPHA[trim((string) $record['CURRENCY'])] ?? 'PLN';

            $valueDate = CarbonImmutable::parse(trim($record['TRANSACTION_DATE']));
            $payoutDate = $record['PAYOUT_DATE'] ? CarbonImmutable::parse(trim($record['PAYOUT_DATE'])) : null;

            yield new ParsedRow(
                rowNo:           $rowNo,
                rowType:         $isRefund ? SourceRow::TYPE_REFUND : SourceRow::TYPE_SALE,
                valueDate:       $valueDate,
                postedAt:        $payoutDate ?? $valueDate,
                grossAmount:     $gross,
                netAmount:       $net,
                commissionAmount: $fee,
                currency:        $currencyAlpha,
                externalId:      trim($record['TRANSACTION_ID']),
                raw:             $record,
                extra: [
                    'merchant_id'              => trim((string) $record['MERCHANT_ID']),
                    'sponsored_merchant_id'    => trim((string) $record['SPONSORED_MERCHANT_ID']),
                    'brand'                    => trim((string) $record['BRAND']),
                    'payout_account'           => trim((string) $record['PAYOUT_ACCOUNT']),
                    'payout_date'              => $payoutDate?->format('Y-m-d'),
                    'payout_amount'            => (string) $payout,
                    'original_transaction_id'  => trim((string) ($record['ORIGINAL_TRANSACTION_ID'] ?? '')) ?: null,
                    'refund_account_number'    => trim((string) ($record['REFUND_ACCOUNT_NUMBER'] ?? '')) ?: null,
                ],
            );
        }
    }
}
