<?php

namespace App\Ledger;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\MasscollectDomain;

/**
 * Rozwiązywanie kont księgowych dla bytów bankowych.
 *
 * Konta zakłada seeder, ale procesy księgujące i tak przechodzą przez
 * firstOrCreate - nowy rachunek albo nowa domena nie mogą zablokować
 * księgowania wpłaty, która już wpłynęła.
 */
class LedgerAccounts
{
    public function forBankAccount(BankAccount $bankAccount): Account
    {
        return Account::firstOrCreate(
            ['code' => 'BANK.'.$bankAccount->iban],
            [
                'name'            => $bankAccount->name,
                'type'            => Account::TYPE_BANK,
                'currency'        => $bankAccount->currency,
                'bank_account_id' => $bankAccount->id,
            ],
        );
    }

    /** Zobowiązanie wobec konsumenta domeny - druga strona wpłaty na mikro rachunek. */
    public function forDomain(MasscollectDomain $domain): Account
    {
        return Account::firstOrCreate(
            ['code' => 'MASSCOLLECT.'.strtoupper($domain->code)],
            [
                'name'                  => 'Wpłaty masscollect - '.$domain->name,
                'type'                  => Account::TYPE_MASSCOLLECT,
                'currency'              => 'PLN',
                'masscollect_domain_id' => $domain->id,
                'tenant_id'             => $domain->tenant_id,
            ],
        );
    }

    public function suspense(): Account
    {
        return Account::firstOrCreate(
            ['code' => config('ledger.accounts.suspense', 'SUSPENSE')],
            [
                'name'     => 'Wpłaty niezidentyfikowane',
                'type'     => Account::TYPE_SUSPENSE,
                'currency' => 'PLN',
            ],
        );
    }

    public function byCode(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }
}
