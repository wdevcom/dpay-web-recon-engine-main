<?php

namespace App\Banking\Statements;

use App\Banking\GoConnect\GoConnectGateway;
use App\Models\BankAccount;
use Carbon\CarbonImmutable;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\Exceptions\GoConnectException;
use Illuminate\Support\Collection;

/**
 * Odświeża salda rachunków z banku.
 *
 * Uwaga na oczekiwania: GOconnect NIE MA operacji "podaj listę rachunków".
 * Kanał odpowiada wyłącznie na pytanie o rachunek, który już znamy, więc
 * rejestrem rachunków jest tabela `bank_accounts`, a ten proces jedynie
 * potwierdza ich istnienie i pobiera salda. Rachunek, którego bank nie zna
 * albo do którego nie mamy praw, zostaje oznaczony błędem synchronizacji,
 * a nie usunięty - to decyzja operatora, nie automatu.
 */
class SyncBankAccounts
{
    public function __construct(private readonly GoConnectGateway $gateway)
    {
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function handle(?BankAccount $only = null): Collection
    {
        $accounts = $only !== null
            ? collect([$only])
            : BankAccount::query()->where('is_active', true)->get();

        return $accounts->map(fn (BankAccount $account) => $this->syncOne($account));
    }

    public function syncOne(BankAccount $account): BankAccount
    {
        try {
            $balance = $this->gateway->balance($account->iban);

            $account->update([
                'currency'                => $balance->currency?->value ?? $account->currency,
                'available_balance_minor' => $balance->available()?->signedMinorUnits(),
                'booked_balance_minor'    => $balance->booked()?->signedMinorUnits(),
                'balance_synced_at'       => CarbonImmutable::now(),
                'sync_error'              => null,
            ]);
        } catch (GoConnectException $e) {
            // Brak salda bank sygnalizuje błędem, nie pustą odpowiedzią -
            // i akurat tutaj to naprawdę znaczy problem z rachunkiem albo
            // z uprawnieniami, więc nie tłumaczymy tego na "zero".
            $account->update([
                'sync_error'        => substr($e->getMessage(), 0, 250),
                'balance_synced_at' => CarbonImmutable::now(),
            ]);
        }

        return $account->fresh();
    }
}
