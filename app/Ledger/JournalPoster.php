<?php

namespace App\Ledger;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Zapisuje Posting jako JournalEntry + JournalLines, atomowo, pilnując
 * niezmiennika podwójnego zapisu: suma WN === suma MA, minimum 2 wiersze.
 *
 * Numery są kolejne w obrębie roku daty księgowania: JE-2026-000001...
 * Licznik siedzi w `ledger_sequences` z blokadą wiersza, a nie w
 * MAX(entry_no)+1 - dwa workery kolejki policzyłyby to samo MAX i drugi
 * wpis padłby na unikalnym indeksie w środku przetwarzania wyciągu.
 */
class JournalPoster
{
    public function post(Posting $posting): JournalEntry
    {
        if (count($posting->lines) < 2) {
            throw new \InvalidArgumentException('Zapis wymaga co najmniej 2 wierszy (otrzymano '.count($posting->lines).').');
        }

        $totalDr = BigDecimal::zero();
        $totalCr = BigDecimal::zero();
        $currencies = [];
        foreach ($posting->lines as $line) {
            $totalDr = $totalDr->plus($line->debit);
            $totalCr = $totalCr->plus($line->credit);
            $currencies[$line->currency] = true;
        }

        if (! $totalDr->isEqualTo($totalCr)) {
            throw new UnbalancedEntryException((string) $totalDr, (string) $totalCr, $posting->description);
        }
        if ($totalDr->isZero()) {
            throw new \InvalidArgumentException('Zapis o sumie zerowej nie ma sensu księgowego.');
        }
        if (count($currencies) !== 1) {
            throw new \InvalidArgumentException('Zapisy wielowalutowe nie są obsługiwane (otrzymano: '.implode(',', array_keys($currencies)).').');
        }

        return DB::transaction(function () use ($posting, $totalDr, $currencies) {
            $year = CarbonImmutable::instance($posting->postedAt)->year;
            $entry = JournalEntry::create([
                'entry_no'           => $this->nextEntryNo($year),
                'posted_at'          => $posting->postedAt,
                'value_date'         => $posting->valueDate,
                'description'        => $posting->description,
                'source_type'        => $posting->sourceType,
                'statement_entry_id' => $posting->statementEntryId,
                'created_by'         => $posting->createdBy,
                'status'             => JournalEntry::STATUS_POSTED,
                'total'              => (string) $totalDr,
                'currency'           => array_key_first($currencies),
            ]);

            foreach ($posting->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line->account->id,
                    'debit'            => (string) $line->debit,
                    'credit'           => (string) $line->credit,
                    'currency'         => $line->currency,
                    'memo'             => $line->memo,
                    'external_ref'     => $line->externalRef,
                ]);
            }

            return $entry->fresh('lines');
        });
    }

    /**
     * Storno: zapis lustrzany plus dowiązanie w obie strony. Oryginał
     * zostaje w księdze - księgi się nie poprawia, tylko koryguje.
     */
    public function reverse(JournalEntry $entry, string $reason, ?int $actorId = null): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw new \DomainException('Stornować można wyłącznie zapis o statusie posted.');
        }

        $reversal = Posting::for(
            JournalEntry::SRC_REVERSAL,
            'STORNO '.$entry->entry_no.' - '.$reason,
            CarbonImmutable::now()->toDateTime(),
        )->currency($entry->currency)->createdBy($actorId)->statementEntry($entry->statement_entry_id);

        foreach ($entry->lines as $line) {
            if (! BigDecimal::of((string) $line->credit)->isZero()) {
                $reversal->debit($line->account, (string) $line->credit, $line->memo, $line->external_ref);
            } else {
                $reversal->credit($line->account, (string) $line->debit, $line->memo, $line->external_ref);
            }
        }

        return DB::transaction(function () use ($entry, $reversal) {
            $new = $this->post($reversal);
            $entry->update([
                'status'               => JournalEntry::STATUS_REVERSED,
                'reversed_by_entry_id' => $new->id,
            ]);
            return $new;
        });
    }

    private function nextEntryNo(int $year): string
    {
        $scope = sprintf('%s-%d', config('ledger.entry_prefix', 'JE'), $year);

        DB::table('ledger_sequences')->insertOrIgnore([
            'scope'      => $scope,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (int) DB::table('ledger_sequences')
            ->where('scope', $scope)
            ->lockForUpdate()
            ->value('next_value');

        DB::table('ledger_sequences')
            ->where('scope', $scope)
            ->update(['next_value' => $current + 1, 'updated_at' => now()]);

        return sprintf('%s-%06d', $scope, $current);
    }
}
