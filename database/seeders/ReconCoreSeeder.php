<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Seeds the chart of accounts + provider catalog with sensible defaults
 * for Payments Lab / dpay. Idempotent (uses updateOrCreate by code).
 *
 * After running, every provider has its clearing & commission account wired,
 * and the operational bank account (PKO 96253...01) is created.
 */
class ReconCoreSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Master bank account
        $bankPko = Account::updateOrCreate(
            ['code' => 'BANK.PKO.96253000082060107214200001'],
            [
                'name'         => 'PKO BP — rachunek operacyjny',
                'type'         => Account::TYPE_BANK,
                'currency'     => 'PLN',
                'external_ref' => '96253000082060107214200001',
                'is_active'    => true,
            ],
        );

        // 2. Suspense / discrepancy / commission revenue
        $suspense = Account::updateOrCreate(['code' => 'SUSPENSE'], [
            'name' => 'Konto przejściowe (suspense)',
            'type' => Account::TYPE_SUSPENSE,
            'currency' => 'PLN',
        ]);
        Account::updateOrCreate(['code' => 'DISCREPANCY.SHORT'], [
            'name' => 'Rozjazdy in-minus (niedobór)',
            'type' => Account::TYPE_DISCREPANCY,
            'currency' => 'PLN',
        ]);
        Account::updateOrCreate(['code' => 'DISCREPANCY.OVER'], [
            'name' => 'Rozjazdy in-plus (nadpłata)',
            'type' => Account::TYPE_DISCREPANCY,
            'currency' => 'PLN',
        ]);
        Account::updateOrCreate(['code' => 'MERCHANT.payable'], [
            'name' => 'Zobowiązania wobec merchantów',
            'type' => Account::TYPE_MERCHANT,
            'currency' => 'PLN',
        ]);

        // 3. One clearing + commission account per provider
        foreach (config('recon.providers') as $code => $meta) {
            $provider = Provider::updateOrCreate(
                ['code' => $code],
                [
                    'name'                 => $meta['name'],
                    'default_currency'     => $meta['currency'],
                    'match_window_days'    => config("recon.matching.{$code}.window_days", config('recon.matching.default_window_days')),
                    'amount_tolerance'     => config("recon.matching.{$code}.amount_tolerance", config('recon.matching.default_amount_tolerance')),
                    'is_active'            => true,
                ],
            );

            // bank/internal providers don't have a clearing pair
            if (in_array($code, ['bank_pko', 'bank_mt940', 'internal'], true)) {
                if ($code === 'bank_pko') {
                    $provider->update(['bank_account_id' => $bankPko->id]);
                }
                continue;
            }

            $clearing = Account::updateOrCreate(
                ['code' => 'CLEARING.'.strtoupper($code)],
                [
                    'name'        => 'Clearing — '.$meta['name'],
                    'type'        => Account::TYPE_CLEARING,
                    'currency'    => $meta['currency'],
                    'provider_id' => $provider->id,
                ],
            );
            $commission = Account::updateOrCreate(
                ['code' => 'COMMISSION.'.strtoupper($code)],
                [
                    'name'        => 'Prowizja — '.$meta['name'],
                    'type'        => Account::TYPE_COMMISSION,
                    'currency'    => $meta['currency'],
                    'provider_id' => $provider->id,
                ],
            );

            $provider->update([
                'clearing_account_id'   => $clearing->id,
                'commission_account_id' => $commission->id,
                'bank_account_id'       => $bankPko->id,
            ]);
        }
    }
}
