<?php

namespace App\Banking\Reconcile;

use App\Models\Discrepancy;
use App\Models\MasscollectDomain;
use App\Models\Reconciliation;
use App\Models\StatementEntry;
use App\Models\Tenant;
use App\Models\VirtualAccount;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Zestawia to, co widzimy w banku, z tym, co konsument uważa, że powinno
 * wpłynąć.
 *
 * Konsument (np. dpay-web-manager) przysyła swoją listę: referencja
 * i oczekiwana kwota. My mamy swoją, zbudowaną niezależnie z wyciągów
 * bankowych. Wynikiem są cztery kubełki, a nie jedna liczba - "różnica
 * zero" bez rozbicia na pozycje nie mówi, czy nic się nie rozjechało, czy
 * dwa błędy się zniosły.
 */
class RunReconciliation
{
    /**
     * @param  array<int, array{owner_ref: string, amount_minor: int}>  $counterpartyItems
     * @return array{reconciliation: Reconciliation, matched: list<array>, amount_mismatch: list<array>, missing_in_bank: list<array>, missing_at_consumer: list<array>}
     */
    public function handle(
        Tenant $tenant,
        DateTimeInterface $periodFrom,
        DateTimeInterface $periodTo,
        array $counterpartyItems,
        ?MasscollectDomain $domain = null,
        string $currency = 'PLN',
    ): array {
        $ours = $this->ourPaymentsByOwnerRef($tenant, $periodFrom, $periodTo, $domain);

        $theirs = [];
        foreach ($counterpartyItems as $item) {
            $ref = (string) $item['owner_ref'];
            $theirs[$ref] = ($theirs[$ref] ?? 0) + (int) $item['amount_minor'];
        }

        $matched = $amountMismatch = $missingInBank = $missingAtConsumer = [];

        foreach ($theirs as $ref => $expected) {
            $actual = $ours[$ref] ?? null;

            if ($actual === null) {
                $missingInBank[] = ['owner_ref' => $ref, 'expected_minor' => $expected];
                continue;
            }

            if ($actual === $expected) {
                $matched[] = ['owner_ref' => $ref, 'amount_minor' => $actual];
                continue;
            }

            $amountMismatch[] = [
                'owner_ref'      => $ref,
                'expected_minor' => $expected,
                'actual_minor'   => $actual,
                'difference'     => $actual - $expected,
            ];
        }

        foreach ($ours as $ref => $actual) {
            if (! array_key_exists($ref, $theirs)) {
                $missingAtConsumer[] = ['owner_ref' => $ref, 'actual_minor' => $actual];
            }
        }

        $bankTotal = array_sum($ours);
        $counterpartyTotal = array_sum($theirs);

        $reconciliation = DB::transaction(function () use (
            $tenant, $domain, $periodFrom, $periodTo, $currency,
            $bankTotal, $counterpartyTotal, $matched, $amountMismatch, $missingInBank, $missingAtConsumer
        ) {
            $unmatched = count($amountMismatch) + count($missingInBank) + count($missingAtConsumer);

            $reconciliation = Reconciliation::create([
                'group_no'                 => $this->nextGroupNo((int) $periodTo->format('Y')),
                'tenant_id'                => $tenant->id,
                'masscollect_domain_id'    => $domain?->id,
                'bank_account_id'          => $domain?->bank_account_id,
                'period_from'              => $periodFrom->format('Y-m-d'),
                'period_to'                => $periodTo->format('Y-m-d'),
                'currency'                 => $currency,
                'bank_total_minor'         => $bankTotal,
                'counterparty_total_minor' => $counterpartyTotal,
                'difference_minor'         => $bankTotal - $counterpartyTotal,
                'matched_count'            => count($matched),
                'unmatched_count'          => $unmatched,
                'status'                   => $unmatched === 0 && $bankTotal === $counterpartyTotal
                    ? Reconciliation::STATUS_BALANCED
                    : Reconciliation::STATUS_WITH_DISCREPANCY,
            ]);

            $this->recordDiscrepancies($reconciliation, $tenant, $currency, $amountMismatch, $missingInBank, $missingAtConsumer);

            return $reconciliation;
        });

        return [
            'reconciliation'      => $reconciliation,
            'matched'             => $matched,
            'amount_mismatch'     => $amountMismatch,
            'missing_in_bank'     => $missingInBank,
            'missing_at_consumer' => $missingAtConsumer,
        ];
    }

    /**
     * Nasza strona: zaksięgowane wpłaty na mikro rachunki konsumenta
     * w okresie, zsumowane po referencji konsumenta.
     *
     * @return array<string, int>
     */
    private function ourPaymentsByOwnerRef(
        Tenant $tenant,
        DateTimeInterface $periodFrom,
        DateTimeInterface $periodTo,
        ?MasscollectDomain $domain,
    ): array {
        $query = StatementEntry::query()
            ->join('virtual_accounts', 'virtual_accounts.id', '=', 'statement_entries.virtual_account_id')
            ->where('statement_entries.match_status', StatementEntry::MATCH_MATCHED)
            ->where('statement_entries.direction', StatementEntry::DIR_CREDIT)
            ->where('virtual_accounts.tenant_id', $tenant->id)
            ->whereBetween('statement_entries.booking_date', [
                $periodFrom->format('Y-m-d'),
                $periodTo->format('Y-m-d'),
            ]);

        if ($domain !== null) {
            $query->where('virtual_accounts.masscollect_domain_id', $domain->id);
        }

        return $query
            ->groupBy('virtual_accounts.owner_ref')
            ->selectRaw('virtual_accounts.owner_ref as owner_ref, SUM(statement_entries.amount_minor) as total')
            ->pluck('total', 'owner_ref')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function recordDiscrepancies(
        Reconciliation $reconciliation,
        Tenant $tenant,
        string $currency,
        array $amountMismatch,
        array $missingInBank,
        array $missingAtConsumer,
    ): void {
        $rows = [];

        foreach ($amountMismatch as $item) {
            $rows[] = [
                'type'        => Discrepancy::TYPE_AMOUNT_MISMATCH,
                'owner_ref'   => $item['owner_ref'],
                'amount'      => $item['difference'],
                'description' => sprintf(
                    'Konsument oczekuje %d gr, w banku %d gr.',
                    $item['expected_minor'],
                    $item['actual_minor'],
                ),
                'severity' => 'warning',
            ];
        }

        foreach ($missingInBank as $item) {
            $rows[] = [
                'type'        => Discrepancy::TYPE_MISSING_IN_BANK,
                'owner_ref'   => $item['owner_ref'],
                'amount'      => -$item['expected_minor'],
                'description' => 'Konsument wykazuje wpłatę, której nie ma na rachunku bankowym.',
                'severity'    => 'critical',
            ];
        }

        foreach ($missingAtConsumer as $item) {
            $rows[] = [
                'type'        => Discrepancy::TYPE_MISSING_AT_CONSUMER,
                'owner_ref'   => $item['owner_ref'],
                'amount'      => $item['actual_minor'],
                'description' => 'Wpłata jest w banku, konsument jej nie wykazuje.',
                'severity'    => 'critical',
            ];
        }

        foreach ($rows as $row) {
            Discrepancy::create([
                'reconciliation_id'  => $reconciliation->id,
                'tenant_id'          => $tenant->id,
                'virtual_account_id' => VirtualAccount::where('tenant_id', $tenant->id)
                    ->where('owner_ref', $row['owner_ref'])
                    ->value('id'),
                'type'         => $row['type'],
                'amount_minor' => $row['amount'],
                'currency'     => $currency,
                'description'  => sprintf('[%s] %s', $row['owner_ref'], $row['description']),
                'status'       => Discrepancy::STATUS_PENDING,
                'severity'     => $row['severity'],
            ]);
        }
    }

    private function nextGroupNo(int $year): string
    {
        $scope = sprintf('%s-%d', config('ledger.reconciliation_prefix', 'REC'), $year);

        DB::table('ledger_sequences')->insertOrIgnore([
            'scope'      => $scope,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $current = (int) DB::table('ledger_sequences')->where('scope', $scope)->lockForUpdate()->value('next_value');
        DB::table('ledger_sequences')->where('scope', $scope)->update(['next_value' => $current + 1, 'updated_at' => now()]);

        return sprintf('%s-%06d', $scope, $current);
    }
}
