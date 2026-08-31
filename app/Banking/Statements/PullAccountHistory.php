<?php

namespace App\Banking\Statements;

use App\Banking\GoConnect\GoConnectGateway;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\StatementEntry;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pobiera operacje z banku do `statement_entries`.
 *
 * GOconnect nie ma webhooków - jedyny tryb to odpytywanie, więc pobrania
 * z założenia zachodzą na siebie. Cała odporność siedzi w deduplikacji po
 * odcisku palca; dzięki temu wolno pobierać częściej niż to konieczne, a
 * powtórzenie po błędzie sieci niczego nie dubluje.
 */
class PullAccountHistory
{
    public function __construct(
        private readonly GoConnectGateway $gateway,
        private readonly StatementEntryMapper $mapper,
    ) {
    }

    public function handle(BankAccount $account, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): BankStatement
    {
        $explicitRange = $from !== null || $to !== null;
        $today = new DateTimeImmutable('today');

        $to ??= $today;
        $from ??= $to->modify('-'.(int) config('bnp.sync.history_lookback_days', 7).' days');

        // Przyrost ma sens tylko w obrębie tego samego dnia - numer
        // porządkowy operacji zeruje się o północy.
        $useIncremental = ! $explicitRange
            && $account->last_transaction_number !== null
            && $account->last_transaction_date !== null
            && $account->last_transaction_date->isToday();

        if ($useIncremental) {
            $report = $this->gateway->incrementalHistory($account->iban, $today, (int) $account->last_transaction_number);
            $source = BankStatement::SOURCE_INCREMENTAL;
            $periodFrom = $periodTo = $today;
        } else {
            $report = $this->gateway->history($account->iban, $from, $to);
            $source = BankStatement::SOURCE_HISTORY;
            $periodFrom = $from;
            $periodTo = $to;
        }

        return DB::transaction(function () use ($account, $report, $source, $periodFrom, $periodTo, $today) {
            $statement = BankStatement::create([
                'bank_account_id'    => $account->id,
                'source'             => $source,
                'message_id'         => $report->messageId,
                'period_from'        => $periodFrom->format('Y-m-d'),
                'period_to'          => $periodTo->format('Y-m-d'),
                'entries_count'      => count($report->entries),
                'document_namespace' => $report->documentNamespace,
                'fetched_at'         => CarbonImmutable::now(),
            ]);

            $new = 0;
            $highestToday = (int) ($account->last_transaction_number ?? 0);

            foreach ($report->entries as $entry) {
                $attributes = $this->mapper->toAttributes($account, $entry, $statement->id);

                if (StatementEntry::where('fingerprint', $attributes['fingerprint'])->exists()) {
                    continue;
                }

                StatementEntry::create($attributes);
                $new++;

                if ($attributes['booking_date'] === $today->format('Y-m-d') && $attributes['transaction_number'] !== null) {
                    $highestToday = max($highestToday, (int) $attributes['transaction_number']);
                }
            }

            $statement->update(['new_entries_count' => $new]);

            $account->update([
                'history_synced_at'       => CarbonImmutable::now(),
                'last_transaction_number' => $highestToday > 0 ? $highestToday : $account->last_transaction_number,
                'last_transaction_date'   => $highestToday > 0 ? $today->format('Y-m-d') : $account->last_transaction_date,
            ]);

            return $statement->fresh();
        });
    }
}
