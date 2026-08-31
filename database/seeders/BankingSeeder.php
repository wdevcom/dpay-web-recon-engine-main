<?php

namespace Database\Seeders;

use App\Banking\Masscollect\AccountNumberMask;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\MasscollectDomain;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Zakłada rejestr rachunków, podział domenowy masscollect, konsumentów
 * i plan kont. Idempotentny - można puszczać wielokrotnie.
 */
class BankingSeeder extends Seeder
{
    /**
     * Podział przestrzeni numerów. Cyfra jest częścią numeru rachunku,
     * więc raz nadana nie może się zmienić - zmiana unieważniłaby
     * odczytywanie właściciela z wyciągu dla numerów już wydanych.
     */
    private const DOMAINS = [
        ['digits' => '1', 'code' => 'manager',  'name' => 'dpay-web-manager - rachunek per transakcja',      'lifecycle' => MasscollectDomain::LIFECYCLE_ONE_TIME,   'ttl' => 60 * 24 * 7],
        ['digits' => '2', 'code' => 'eid',      'name' => 'dpay-web-eid - przelew weryfikacyjny',            'lifecycle' => MasscollectDomain::LIFECYCLE_ONE_TIME,   'ttl' => 60 * 24 * 3],
        ['digits' => '3', 'code' => 'esim',     'name' => 'dpay-web-esim - rachunek per lokalizacja',        'lifecycle' => MasscollectDomain::LIFECYCLE_PERSISTENT, 'ttl' => null],
        ['digits' => '4', 'code' => 'partners', 'name' => 'Rozliczenia partnerskie - rachunek per partner',  'lifecycle' => MasscollectDomain::LIFECYCLE_PERSISTENT, 'ttl' => null],
    ];

    private const TENANTS = [
        'manager'  => 'dpay-web-manager',
        'eid'      => 'dpay-web-eid',
        'esim'     => 'dpay-web-esim',
        'partners' => 'Rozliczenia partnerskie',
    ];

    public function run(): void
    {
        $tenants = [];
        foreach (self::TENANTS as $code => $name) {
            $tenants[$code] = Tenant::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $master = BankAccount::updateOrCreate(
            ['iban' => $this->masterIban()],
            [
                'name'      => config('bnp.masscollect.master_iban')
                    ? 'BNP Paribas - rachunek masowy (masscollect)'
                    : 'BNP Paribas - rachunek masowy [PLACEHOLDER: ustaw BNP_MASSCOLLECT_MASTER_IBAN]',
                'currency'  => 'PLN',
                'purpose'   => BankAccount::PURPOSE_MASSCOLLECT,
                'is_active' => (bool) config('bnp.masscollect.master_iban'),
            ],
        );

        Account::updateOrCreate(
            ['code' => 'BANK.'.$master->iban],
            [
                'name'            => $master->name,
                'type'            => Account::TYPE_BANK,
                'currency'        => 'PLN',
                'bank_account_id' => $master->id,
            ],
        );

        foreach (self::DOMAINS as $definition) {
            $domain = MasscollectDomain::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'digits'              => $definition['digits'],
                    'name'                => $definition['name'],
                    'lifecycle'           => $definition['lifecycle'],
                    'tenant_id'           => $tenants[$definition['code']]->id ?? null,
                    'bank_account_id'     => $master->id,
                    'default_ttl_minutes' => $definition['ttl'],
                    'is_active'           => true,
                ],
            );

            Account::updateOrCreate(
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

        Account::updateOrCreate(['code' => 'SUSPENSE'], [
            'name' => 'Wpłaty niezidentyfikowane', 'type' => Account::TYPE_SUSPENSE, 'currency' => 'PLN',
        ]);
        Account::updateOrCreate(['code' => 'DISCREPANCY.OVER'], [
            'name' => 'Rozjazdy in-plus', 'type' => Account::TYPE_DISCREPANCY, 'currency' => 'PLN',
        ]);
        Account::updateOrCreate(['code' => 'DISCREPANCY.SHORT'], [
            'name' => 'Rozjazdy in-minus', 'type' => Account::TYPE_DISCREPANCY, 'currency' => 'PLN',
        ]);
    }

    /**
     * Bez skonfigurowanego rachunku masowego zakładamy numer wyliczony
     * z maski (domena 0, sekwencja 0). Jest poprawny rachunkowo, więc
     * aplikacja i testy działają, ale rachunek zostaje nieaktywny i ma to
     * wprost w nazwie - żeby nikt nie wziął go za prawdziwy.
     */
    private function masterIban(): string
    {
        $configured = config('bnp.masscollect.master_iban');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $mask = AccountNumberMask::fromConfig();

        return 'PL'.$mask->build(str_repeat('0', $mask->domainDigits), 0);
    }
}
