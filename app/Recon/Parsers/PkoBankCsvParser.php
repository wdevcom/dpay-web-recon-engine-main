<?php

namespace App\Recon\Parsers;

use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Support\Money;
use Carbon\CarbonImmutable;
use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Parser for PKO Bank Polski "Lista operacji" CSV export.
 *
 * Format (sample):
 *   Numer rachunku: 96253...
 *   Właściciel: PAYMENTS LAB ...
 *   Historia operacji za okres od YYYY-MM-DD do DD.MM.YYYY
 *   Liczba operacji: N
 *   Suma uznań: X PLN
 *   Suma obciążeń: Y PLN
 *   Data księgowania,Data operacji,Rodzaj operacji,Kwota,Waluta,Dane kontrahenta,Numer rachunku kontrahenta,Tytuł operacji,Saldo po operacji
 *   <data rows...>
 *
 * Inflows are recorded as TYPE_BANK_CREDIT (positive amount),
 * outflows as TYPE_BANK_DEBIT (negative amount).
 */
class PkoBankCsvParser implements StatementParser
{
    public function format(): string
    {
        return 'csv_pko';
    }

    public function supports(SourceDocument $doc, string $absolutePath): bool
    {
        if ($doc->format === $this->format()) {
            return true;
        }
        $head = $this->readHead($absolutePath, 1024);
        return str_starts_with($head, 'Numer rachunku:') && str_contains($head, 'Historia operacji');
    }

    public function parse(SourceDocument $doc, string $absolutePath): iterable
    {
        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new \RuntimeException("Cannot read {$absolutePath}");
        }
        // PKO ships UTF-8 BOM sometimes; strip
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $lines = preg_split("/\r\n|\n|\r/", (string) $contents);
        // Skip header until we find the column header row.
        $columnHeader = 'Data księgowania,Data operacji,Rodzaj operacji,Kwota';
        $dataStart = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, $columnHeader)) {
                $dataStart = $i;
                break;
            }
        }
        if ($dataStart === null) {
            throw new \RuntimeException('PKO CSV: header row not found.');
        }

        $csvText = implode("\n", array_slice($lines, $dataStart));
        $csv = Reader::createFromString($csvText);
        $csv->setDelimiter(',');
        $csv->setHeaderOffset(0);

        $rowNo = 0;
        foreach (Statement::create()->process($csv) as $record) {
            $rowNo++;

            // skip blank rows (trailing empty line)
            $kwotaRaw = $record['Kwota'] ?? '';
            if ($kwotaRaw === '' && empty($record['Data księgowania'])) {
                continue;
            }

            $valueDate = CarbonImmutable::createFromFormat('d-m-Y', trim($record['Data operacji'] ?? ''))
                ?: CarbonImmutable::createFromFormat('Y-m-d', trim($record['Data operacji'] ?? ''));
            $postedAt = CarbonImmutable::createFromFormat('d-m-Y', trim($record['Data księgowania'] ?? ''))
                ?: $valueDate;

            $gross = Money::of($kwotaRaw);
            $rowType = $gross->isNegative() ? SourceRow::TYPE_BANK_DEBIT : SourceRow::TYPE_BANK_CREDIT;

            yield new ParsedRow(
                rowNo:           $rowNo,
                rowType:         $rowType,
                valueDate:       $valueDate,
                postedAt:        $postedAt,
                grossAmount:     $gross,
                netAmount:       $gross,
                commissionAmount: null,
                currency:        trim($record['Waluta'] ?? 'PLN'),
                externalId:      $this->reference($record),
                raw:             $record,
                extra: [
                    'counterparty_name'    => trim($record['Dane kontrahenta'] ?? ''),
                    'counterparty_account' => trim($record['Numer rachunku kontrahenta'] ?? ''),
                    'title'                => trim($record['Tytuł operacji'] ?? ''),
                    'balance_after'        => trim($record['Saldo po operacji'] ?? ''),
                    'operation_kind'       => trim($record['Rodzaj operacji'] ?? ''),
                ],
            );
        }
    }

    private function reference(array $record): ?string
    {
        // Title often contains the provider's payout id (e.g. "PSP-000002919").
        $title = trim($record['Tytuł operacji'] ?? '');
        if ($title === '') {
            return null;
        }
        return $title;
    }

    private function readHead(string $path, int $bytes): string
    {
        $fp = fopen($path, 'r');
        if ($fp === false) {
            return '';
        }
        $buf = fread($fp, $bytes) ?: '';
        fclose($fp);
        return preg_replace('/^\xEF\xBB\xBF/', '', $buf) ?? '';
    }
}
