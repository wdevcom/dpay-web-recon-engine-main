<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kanał GOconnect Biznes
    |--------------------------------------------------------------------------
    | Dwa niezależne certyfikaty: komunikacyjny (dwustronny SSL, obowiązkowy)
    | i autoryzacyjny (podpis XAdES zleceń, potrzebny dopiero przy wypłatach).
    | Ścieżki i hasła wyłącznie ze środowiska - nigdy w repozytorium.
    */
    'goconnect' => [
        // null = produkcja (https://connect.bnpparibas.pl/bnpp-cdc/cdc00101).
        // Adres testowy bank nadaje indywidualnie; musi być HTTPS.
        'endpoint'          => env('BNP_ENDPOINT'),
        'client_id'         => env('BNP_CLIENT_ID'),
        'initiating_party'  => env('BNP_INITIATING_PARTY', 'dpayrecon'),
        'message_id_prefix' => env('BNP_MESSAGE_ID_PREFIX', 'DPAY'),
        'timeout_seconds'   => (int) env('BNP_TIMEOUT_SECONDS', 120),
        'ca_bundle_path'    => env('BNP_CA_BUNDLE_PATH'),

        'communication_certificate' => [
            'cert_path'  => env('BNP_CERT_PATH'),
            'key_path'   => env('BNP_KEY_PATH'),
            'passphrase' => env('BNP_KEY_PASSPHRASE'),
        ],

        'authorization_certificate' => [
            'pkcs12_path' => env('BNP_SIGN_PKCS12_PATH'),
            'password'    => env('BNP_SIGN_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maska masscollect (rachunki wirtualne)
    |--------------------------------------------------------------------------
    | Polski NRB ma 26 cyfr: [2 cyfry kontrolne][8 cyfr numeru rozliczeniowego]
    | [16 cyfr numeru rachunku]. Bank nadaje prefiks w części 16-cyfrowej,
    | reszta należy do nas i to my jesteśmy rejestrem tych numerów - GOconnect
    | nie ma operacji zwracającej listę rachunków wirtualnych.
    |
    | Podział 16 cyfr:
    |   [client_prefix][domain_digits][sekwencja - reszta]
    |
    | Cyfra domeny pozwala rozpoznać właściciela wpłaty bez zapytania do bazy,
    | co ma znaczenie przy odtwarzaniu rozliczeń z samego wyciągu.
    */
    'masscollect' => [
        'bank_code'     => env('BNP_MASSCOLLECT_BANK_CODE', '16001462'),
        'client_prefix' => env('BNP_MASSCOLLECT_CLIENT_PREFIX', '0022'),
        'domain_digits' => (int) env('BNP_MASSCOLLECT_DOMAIN_DIGITS', 1),
        'master_iban'   => env('BNP_MASSCOLLECT_MASTER_IBAN'),

        // Ile numerów zostawić w rezerwie na koniec puli, żeby wyczerpanie
        // zakresu zgłosiło się alertem, a nie błędem w środku dnia.
        'low_watermark' => (int) env('BNP_MASSCOLLECT_LOW_WATERMARK', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pobieranie historii
    |--------------------------------------------------------------------------
    | Bank nie ma webhooków - jedyny tryb to odpytywanie. Pobieranie
    | przyrostowe idzie po numerze porządkowym operacji w danym dniu
    | (Refs/MsgId), nie po dacie, więc numer trzeba pamiętać między
    | wywołaniami (bank_accounts.last_transaction_number).
    */
    'sync' => [
        'history_lookback_days' => (int) env('BNP_HISTORY_LOOKBACK_DAYS', 7),
        'statement_format'      => env('BNP_STATEMENT_FORMAT', 'XML'),
    ],

];
