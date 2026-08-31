<?php

namespace App\Banking\GoConnect;

use App\Models\BnpOperation;
use DateTimeImmutable;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Payload\GetAccountBalancePayload;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Payload\GetAccountReportPayload;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Payload\GetIncrementalAccountReportPayload;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Payload\GetMbrStatementPayload;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Response\AccountBalanceResponse;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Response\AccountReportResponse;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Accounts\Response\StatementResponse;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\Exceptions\GoConnectTransportException;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\BankAccountNumber;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\DateRange;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\MessageId;

/**
 * Jedyne wejście do banku z tej aplikacji.
 *
 * Każde wywołanie zostawia wpis w `bnp_operations` ZANIM komunikat pójdzie
 * do banku. Dla operacji odczytowych to diagnostyka, ale ścieżka jest ta
 * sama, którą będą musiały pójść zlecenia - a tam zapisany z góry MsgId to
 * jedyny sposób, żeby po utraconej odpowiedzi ustalić, czy bank przyjął
 * dyspozycję, zamiast ponawiać i dublować przelew.
 */
class GoConnectGateway
{
    public function __construct(private readonly GoConnectFactory $factory)
    {
    }

    public function isConfigured(): bool
    {
        return $this->factory->isConfigured();
    }

    /** Saldo rachunku - dostępne i księgowe jednym wywołaniem. */
    public function balance(string $iban): AccountBalanceResponse
    {
        $client = $this->factory->client();
        $messageId = $client->newMessageId();

        return $this->run('GetAccountBalance', $messageId, $iban, fn () => $client->accounts()->getAccountBalance(
            new GetAccountBalancePayload($messageId, BankAccountNumber::fromString($iban))
        ));
    }

    /** Historia operacji za zakres dat (camt.052). */
    public function history(string $iban, DateTimeImmutable $from, DateTimeImmutable $to): AccountReportResponse
    {
        $client = $this->factory->client();
        $messageId = $client->newMessageId();

        return $this->run('GetAccountReport', $messageId, $iban, fn () => $client->accounts()->getAccountReport(
            new GetAccountReportPayload($messageId, BankAccountNumber::fromString($iban), DateRange::create($from, $to))
        ));
    }

    /**
     * Przyrost po numerze porządkowym operacji. Numer jest liczony W DANYM
     * DNIU, więc para (dzień, numer) jest nierozłączna - po północy licznik
     * startuje od nowa.
     */
    public function incrementalHistory(string $iban, DateTimeImmutable $day, int $afterTransactionNumber): AccountReportResponse
    {
        $client = $this->factory->client();
        $messageId = $client->newMessageId();

        return $this->run('GetIncrementalAccountReport', $messageId, $iban, fn () => $client->accounts()->getIncrementalAccountReport(
            new GetIncrementalAccountReportPayload($messageId, BankAccountNumber::fromString($iban), $day, $afterTransactionNumber)
        ));
    }

    /** Wyciąg z rachunków wirtualnych (MBR) za zakres dat. */
    public function mbrStatement(string $iban, DateTimeImmutable $from, DateTimeImmutable $to): StatementResponse
    {
        $client = $this->factory->client();
        $messageId = $client->newMessageId();

        return $this->run('GetMBRStatement', $messageId, $iban, fn () => $client->accounts()->getMbrStatement(
            new GetMbrStatementPayload($messageId, BankAccountNumber::fromString($iban), DateRange::create($from, $to))
        ));
    }

    /**
     * @template T
     * @param  callable(): T  $call
     * @return T
     */
    private function run(string $operation, MessageId $messageId, ?string $iban, callable $call): mixed
    {
        $log = BnpOperation::create([
            'operation'    => $operation,
            'message_id'   => $messageId->value,
            'account_iban' => $iban,
            'status'       => BnpOperation::STATUS_SENT,
        ]);

        $startedAt = microtime(true);

        try {
            $result = $call();

            $log->update([
                'status'      => BnpOperation::STATUS_OK,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            return $result;
        } catch (\Throwable $e) {
            // Timeout albo zerwane połączenie przy operacji zmieniającej stan
            // znaczy "nie wiadomo" - i to też musi zostać zapisane, bo od tego
            // zależy, czy wolno ponowić.
            $uncertain = $e instanceof GoConnectTransportException && $e->isDeliveryUncertain();

            $log->update([
                'status'        => $uncertain ? BnpOperation::STATUS_UNCERTAIN : BnpOperation::STATUS_FAILED,
                'duration_ms'   => (int) ((microtime(true) - $startedAt) * 1000),
                'error_class'   => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
