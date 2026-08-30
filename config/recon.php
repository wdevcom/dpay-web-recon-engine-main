<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider catalog
    |--------------------------------------------------------------------------
    | Static metadata about each supported provider. The providers table in
    | the recon DB carries the live tuning (clearing account, SFTP creds,
    | tolerances) — this catalog only declares which codes/formats exist.
    */
    'providers' => [
        'sibs'       => ['name' => 'SIBS Pagamentos',      'format' => 'sibs_csv',         'currency' => 'PLN'],
        'blik'       => ['name' => 'BLIK / Nest Bank PSP', 'format' => 'blik_psp_txt',     'currency' => 'PLN'],
        'payu'       => ['name' => 'PayU',                 'format' => 'payu_csv',         'currency' => 'PLN'],
        'paymentero' => ['name' => 'Paymentero',           'format' => 'paymentero_csv',   'currency' => 'PLN'],
        'bank_pko'   => ['name' => 'PKO Bank Polski',      'format' => 'csv_pko',          'currency' => 'PLN'],
        'bank_mt940' => ['name' => 'Bank MT940',           'format' => 'mt940',            'currency' => 'PLN'],
        'internal'   => ['name' => 'dpay internal txns',   'format' => 'internal',         'currency' => 'PLN'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Matcher defaults
    |--------------------------------------------------------------------------
    */
    'matching' => [
        'default_window_days' => 3,
        'default_amount_tolerance' => '0.00',
        // SIBS payouts hit the bank T+1 in PLN
        'sibs' => ['window_days' => 4, 'amount_tolerance' => '0.01'],
        // BLIK / PSP — same day or T+1
        'blik' => ['window_days' => 2, 'amount_tolerance' => '0.01'],
        // PayU — payout same day
        'payu' => ['window_days' => 2, 'amount_tolerance' => '0.01'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal numbering
    |--------------------------------------------------------------------------
    */
    'journal' => [
        'entry_prefix' => 'JE',
        'reconciliation_prefix' => 'REC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage disk for uploaded source documents
    |--------------------------------------------------------------------------
    */
    'storage_disk' => env('RECON_STORAGE_DISK', 'local'),

];
