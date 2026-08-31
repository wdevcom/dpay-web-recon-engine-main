<?php

namespace App\Banking\Statements;

use App\Banking\Reconcile\VirtualAccountDetector;
use App\Models\BankAccount;
use App\Models\StatementEntry;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Response\AccountEntry;

/**
 * Pozycja camt z biblioteki bankowej -> wiersz `statement_entries`.
 *
 * Odpowiada też za odcisk palca do deduplikacji. Pobieranie z banku zawsze
 * zachodzi na siebie w czasie (przyrost po numerze, potem wyciąg dzienny),
 * więc ta sama operacja przyjdzie do nas kilka razy i musi wylądować
 * dokładnie raz.
 */
class StatementEntryMapper
{
    public function __construct(private readonly VirtualAccountDetector $detector)
    {
    }

    public function toAttributes(BankAccount $account, AccountEntry $entry, ?int $statementId = null): array
    {
        $credited = $entry->isCredit() ? $entry->creditorAccount : null;
        $remittance = $entry->remittanceText();
        $transactionNumber = is_numeric($entry->transactionNumber) ? (int) $entry->transactionNumber : null;

        return [
            'bank_account_id'          => $account->id,
            'bank_statement_id'        => $statementId,
            'direction'                => $entry->isCredit() ? StatementEntry::DIR_CREDIT : StatementEntry::DIR_DEBIT,
            'amount_minor'             => $entry->amount->minorUnits,
            'currency'                 => $entry->amount->currency->value,
            'booking_date'             => $entry->bookingDate?->format('Y-m-d'),
            'value_date'               => $entry->valueDate?->format('Y-m-d'),
            'transaction_number'       => $transactionNumber,
            'instruction_id'           => $entry->instructionId,
            'end_to_end_id'            => $entry->endToEndId,
            'transaction_id'           => $entry->transactionId,
            'counterparty_name'        => $entry->counterparty()?->displayName(),
            'counterparty_account'     => $entry->counterpartyAccount(),
            'remittance_text'          => $remittance !== '' ? $remittance : null,
            'detected_virtual_account' => $this->detector->detect($credited, $remittance),
            'match_status'             => StatementEntry::MATCH_UNMATCHED,
            'fingerprint'              => $this->fingerprint($account, $entry),
            'raw'                      => [
                'bank_transaction_code' => $entry->bankTransactionCode,
                'family'                => $entry->bankTransactionFamilyCode,
                'sub_family'            => $entry->bankTransactionSubFamilyCode,
                'status'                => $entry->status?->value,
                'creditor_account'      => $entry->creditorAccount,
                'debtor_account'        => $entry->debtorAccount,
                'ultimate_debtor'       => $entry->ultimateDebtorName,
                'ultimate_creditor'     => $entry->ultimateCreditorName,
                'remittance_lines'      => $entry->remittanceInformation,
            ],
        ];
    }

    /**
     * Odcisk palca operacji.
     *
     * Gdy bank poda numer porządkowy dnia (`Refs/MsgId`) albo własny
     * identyfikator operacji, to on jest kluczem - jest stabilny między
     * pobraniami. Dopiero bez nich schodzimy do skrótu z treści, i wtedy
     * dwie identyczne wpłaty tego samego dnia, o tej samej kwocie i tytule,
     * od tego samego płatnika, są dla nas nierozróżnialne. To ograniczenie
     * danych banku, nie wybór - ale dotyczy wyłącznie pozycji bez żadnej
     * referencji.
     */
    public function fingerprint(BankAccount $account, AccountEntry $entry): string
    {
        $day = $entry->bookingDate?->format('Y-m-d') ?? $entry->valueDate?->format('Y-m-d') ?? '';

        $key = $entry->transactionId
            ?? $entry->instructionId
            ?? ($entry->transactionNumber !== null ? 'SEQ:'.$entry->transactionNumber : null);

        if ($key !== null) {
            return hash('sha256', implode('|', [$account->id, $day, $key]));
        }

        return hash('sha256', implode('|', [
            $account->id,
            $day,
            $entry->valueDate?->format('Y-m-d') ?? '',
            $entry->isCredit() ? 'C' : 'D',
            $entry->amount->minorUnits,
            $entry->amount->currency->value,
            $entry->endToEndId ?? '',
            $entry->counterpartyAccount() ?? '',
            $entry->remittanceText(),
        ]));
    }
}
