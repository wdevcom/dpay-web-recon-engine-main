<?php

namespace App\Recon\Ledger;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Persists a Posting as a JournalEntry + JournalLines, atomically,
 * enforcing the double-entry invariant: sum(debit) === sum(credit), >= 2 lines.
 *
 * Entry numbers are sequential per calendar year of `posted_at`:
 *   JE-2026-000001, JE-2026-000002, ...
 */
class JournalPoster
{
    public function post(Posting $posting): JournalEntry
    {
        if (count($posting->lines) < 2) {
            throw new \InvalidArgumentException('Journal entry needs at least 2 lines (got '.count($posting->lines).').');
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
            throw new \InvalidArgumentException('Journal entry total cannot be zero.');
        }
        if (count($currencies) !== 1) {
            throw new \InvalidArgumentException('Mixed-currency journal entries are not supported (got: '.implode(',', array_keys($currencies)).').');
        }

        return DB::transaction(function () use ($posting, $totalDr, $currencies) {
            $year = CarbonImmutable::instance($posting->postedAt)->year;
            $entry = JournalEntry::create([
                'entry_no'      => $this->nextEntryNo($year),
                'posted_at'     => $posting->postedAt,
                'value_date'    => $posting->valueDate,
                'description'   => $posting->description,
                'source_type'   => $posting->sourceType,
                'source_row_id' => $posting->sourceRowId,
                'created_by'    => $posting->createdBy,
                'status'        => JournalEntry::STATUS_POSTED,
                'total'         => (string) $totalDr,
                'currency'      => array_key_first($currencies),
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
     * Reverse a posted entry by creating a mirror entry and linking both ways.
     */
    public function reverse(JournalEntry $entry, string $reason, ?int $actorId = null): JournalEntry
    {
        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            throw new \DomainException('Only posted entries can be reversed.');
        }

        $reversal = Posting::for(
            $entry->source_type,
            'REVERSAL of '.$entry->entry_no.' — '.$reason,
            CarbonImmutable::now()->toDateTime(),
        )->currency($entry->currency)->createdBy($actorId);

        foreach ($entry->lines as $line) {
            // swap DR/CR
            if ((string) $line->credit !== '0.00') {
                $reversal->debit($line->account, (string) $line->credit, $line->memo, $line->external_ref);
            } else {
                $reversal->credit($line->account, (string) $line->debit, $line->memo, $line->external_ref);
            }
        }

        return DB::transaction(function () use ($entry, $reversal) {
            $new = $this->post($reversal);
            $entry->update([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_by_entry_id' => $new->id,
            ]);
            return $new;
        });
    }

    private function nextEntryNo(int $year): string
    {
        $prefix = config('recon.journal.entry_prefix', 'JE');
        // SELECT MAX with LIKE — simple, single-writer assumption is OK because
        // this runs inside DB::transaction with row-level lock when needed.
        $like = sprintf('%s-%d-%%', $prefix, $year);
        $last = JournalEntry::lockForUpdate()
            ->where('entry_no', 'like', $like)
            ->orderByDesc('entry_no')
            ->value('entry_no');

        $seq = 1;
        if ($last) {
            $seq = ((int) substr($last, strrpos($last, '-') + 1)) + 1;
        }
        return sprintf('%s-%d-%06d', $prefix, $year, $seq);
    }
}
