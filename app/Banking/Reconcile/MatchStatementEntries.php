<?php

namespace App\Banking\Reconcile;

use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Ledger\JournalPoster;
use App\Ledger\LedgerAccounts;
use App\Ledger\Posting;
use App\Models\BankAccount;
use App\Models\Discrepancy;
use App\Models\JournalEntry;
use App\Models\MasscollectDomain;
use App\Models\StatementEntry;
use App\Models\VirtualAccount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Dopasowuje pozycje z banku do mikro rachunków i księguje je.
 *
 * Zasada, na której to stoi: KAŻDA pozycja z banku zostaje zaksięgowana,
 * także ta nierozpoznana. Konto bankowe w naszej księdze ma odwzorowywać
 * rzeczywiste saldo w BNP - wtedy rozjazd między księgą a saldem z
 * GetAccountBalance jest prawdziwym alarmem, a nie skutkiem tego, że
 * czegoś nie umieliśmy przypisać. Nierozpoznane trafia na konto
 * przejściowe i rodzi rozjazd do wyjaśnienia przez człowieka.
 */
class MatchStatementEntries
{
    public function __construct(
        private readonly VirtualAccountAllocator $allocator,
        private readonly JournalPoster $poster,
        private readonly LedgerAccounts $accounts,
    ) {
    }

    /**
     * @return array{matched: int, suspense: int, skipped: int}
     */
    public function handle(?BankAccount $bankAccount = null, int $limit = 500): array
    {
        $query = StatementEntry::query()
            ->where('match_status', StatementEntry::MATCH_UNMATCHED)
            ->whereNull('journal_entry_id')
            ->orderBy('id')
            ->limit($limit);

        if ($bankAccount !== null) {
            $query->where('bank_account_id', $bankAccount->id);
        }

        $stats = ['matched' => 0, 'suspense' => 0, 'skipped' => 0];

        foreach ($query->get() as $entry) {
            $result = $this->matchOne($entry);
            $stats[$result]++;
        }

        return $stats;
    }

    /** @return 'matched'|'suspense'|'skipped' */
    public function matchOne(StatementEntry $entry): string
    {
        if ($entry->amount_minor === 0) {
            $entry->update([
                'match_status' => StatementEntry::MATCH_IGNORED,
                'match_reason' => 'Pozycja zerowa.',
            ]);

            return 'skipped';
        }

        return DB::transaction(function () use ($entry) {
            $virtualAccount = $entry->detected_virtual_account !== null
                ? $this->allocator->resolve($entry->detected_virtual_account)
                : null;

            if (! $entry->isCredit()) {
                $this->bookToSuspense($entry, 'Obciążenie rachunku - poza zakresem rozliczeń masscollect.');

                return 'suspense';
            }

            if ($virtualAccount === null) {
                $reason = $entry->detected_virtual_account !== null
                    ? 'Numer należy do naszej maski, ale nie ma takiej alokacji.'
                    : 'Wpłata bez rozpoznanego mikro rachunku.';

                $this->bookToSuspense($entry, $reason);

                $this->raiseDiscrepancy(
                    $entry,
                    Discrepancy::TYPE_UNIDENTIFIED_PAYMENT,
                    $reason,
                    $entry->detected_virtual_account !== null ? 'critical' : 'warning',
                );

                return 'suspense';
            }

            if (! $virtualAccount->isOpenForPayments()) {
                $this->bookToSuspense($entry, sprintf(
                    'Wpłata na rachunek o statusie "%s" (%s).',
                    $virtualAccount->status,
                    $virtualAccount->iban,
                ), $virtualAccount);

                $this->raiseDiscrepancy(
                    $entry,
                    Discrepancy::TYPE_PAYMENT_AFTER_EXPIRY,
                    'Wpłata po zamknięciu lub wygaśnięciu mikro rachunku.',
                    'critical',
                    $virtualAccount,
                );

                return 'suspense';
            }

            $this->bookToDomain($entry, $virtualAccount);

            return 'matched';
        });
    }

    private function bookToDomain(StatementEntry $entry, VirtualAccount $virtualAccount): void
    {
        $domain = $virtualAccount->domain;
        $amount = Money::fromMinorUnits($entry->amount_minor);

        $journalEntry = $this->poster->post(
            Posting::for(
                JournalEntry::SRC_MASSCOLLECT,
                sprintf('Wpłata na %s (%s / %s)', $virtualAccount->iban, $domain->code, $virtualAccount->owner_ref),
                $this->postingDate($entry),
            )
                ->valueDate($entry->value_date?->toDateTime())
                ->statementEntry($entry->id)
                ->currency($entry->currency)
                ->debit($this->accounts->forBankAccount($entry->bankAccount), $amount, $entry->counterparty_name, $entry->end_to_end_id)
                ->credit($this->accounts->forDomain($domain), $amount, $virtualAccount->owner_ref, $virtualAccount->iban)
        );

        $entry->update([
            'match_status'       => StatementEntry::MATCH_MATCHED,
            'match_reason'       => null,
            'virtual_account_id' => $virtualAccount->id,
            'journal_entry_id'   => $journalEntry->id,
        ]);

        $received = $virtualAccount->received_amount_minor + $entry->amount_minor;

        $virtualAccount->update([
            'received_amount_minor' => $received,
            'payments_count'        => $virtualAccount->payments_count + 1,
            'first_payment_at'      => $virtualAccount->first_payment_at ?? CarbonImmutable::now(),
            'status'                => VirtualAccount::STATUS_ACTIVE,
        ]);

        // Rachunek jednorazowy wystawiony na konkretną kwotę: różnica jest
        // informacją dla konsumenta, a nie błędem księgowym - pieniądz i tak
        // jest zaksięgowany po stronie właściwej domeny.
        if ($virtualAccount->lifecycle === MasscollectDomain::LIFECYCLE_ONE_TIME
            && $virtualAccount->expected_amount_minor !== null
            && $received !== $virtualAccount->expected_amount_minor) {
            $this->raiseDiscrepancy(
                $entry,
                Discrepancy::TYPE_AMOUNT_MISMATCH,
                sprintf(
                    'Oczekiwano %s, wpłynęło łącznie %s.',
                    Money::formatMinorUnits($virtualAccount->expected_amount_minor),
                    Money::formatMinorUnits($received),
                ),
                'warning',
                $virtualAccount,
                $received - $virtualAccount->expected_amount_minor,
            );
        }
    }

    private function bookToSuspense(StatementEntry $entry, string $reason, ?VirtualAccount $virtualAccount = null): void
    {
        $amount = Money::fromMinorUnits($entry->amount_minor);
        $bank = $this->accounts->forBankAccount($entry->bankAccount);
        $suspense = $this->accounts->suspense();

        $posting = Posting::for(
            JournalEntry::SRC_BANK_STATEMENT,
            'Pozycja bez identyfikacji: '.$reason,
            $this->postingDate($entry),
        )
            ->valueDate($entry->value_date?->toDateTime())
            ->statementEntry($entry->id)
            ->currency($entry->currency);

        if ($entry->isCredit()) {
            $posting->debit($bank, $amount, $entry->counterparty_name, $entry->end_to_end_id)
                ->credit($suspense, $amount, $entry->remittance_text, $entry->detected_virtual_account);
        } else {
            $posting->debit($suspense, $amount, $entry->remittance_text, $entry->detected_virtual_account)
                ->credit($bank, $amount, $entry->counterparty_name, $entry->end_to_end_id);
        }

        $journalEntry = $this->poster->post($posting);

        $entry->update([
            'match_status'       => StatementEntry::MATCH_SUSPENSE,
            'match_reason'       => $reason,
            'virtual_account_id' => $virtualAccount?->id,
            'journal_entry_id'   => $journalEntry->id,
        ]);
    }

    private function raiseDiscrepancy(
        StatementEntry $entry,
        string $type,
        string $description,
        string $severity,
        ?VirtualAccount $virtualAccount = null,
        ?int $amountMinor = null,
    ): void {
        Discrepancy::create([
            'statement_entry_id' => $entry->id,
            'virtual_account_id' => $virtualAccount?->id,
            'tenant_id'          => $virtualAccount?->tenant_id,
            'type'               => $type,
            'amount_minor'       => $amountMinor ?? $entry->amount_minor,
            'currency'           => $entry->currency,
            'description'        => $description,
            'status'             => Discrepancy::STATUS_PENDING,
            'severity'           => $severity,
        ]);
    }

    private function postingDate(StatementEntry $entry): \DateTimeInterface
    {
        return ($entry->booking_date ?? $entry->value_date ?? CarbonImmutable::now())->toDateTime();
    }
}
