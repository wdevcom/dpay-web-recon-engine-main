<?php

return [
    'entry_prefix'           => env('LEDGER_ENTRY_PREFIX', 'JE'),
    'reconciliation_prefix'  => env('LEDGER_RECONCILIATION_PREFIX', 'REC'),

    /*
    | Konta systemowe wołane po kodzie przez procesy księgujące. Kody muszą
    | istnieć w tabeli accounts - zakłada je seeder.
    */
    'accounts' => [
        'suspense'           => 'SUSPENSE',
        'discrepancy_over'   => 'DISCREPANCY.OVER',
        'discrepancy_short'  => 'DISCREPANCY.SHORT',
    ],
];
