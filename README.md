# dpay banking - mikroserwis rachunków i rozliczeń BNP

Warstwa między usługami dpay a bankiem. Jedyny obsługiwany bank to
**BNP Paribas Bank Polska (GOconnect Biznes)**, kanał host-to-host
SOAP + ISO 20022, przez bibliotekę `dpayglobal/dpay-libary-bnpparibas-goconnect-pl-src`.

Serwis odpowiada za trzy rzeczy:

1. **Rejestr rachunków** w BNP wraz z saldami odświeżanymi z banku.
2. **Masscollect** - wydawanie i ewidencja mikro rachunków (rachunków
   wirtualnych) z podziałem domenowym.
3. **Reconcile** - własna księga podwójnego zapisu odwzorowująca bank oraz
   zestawianie jej z danymi usług konsumenckich.

```
dpay-web-manager  ─┐
dpay-web-eid      ─┼─►  dpay banking  ──SOAP/ISO 20022──►  BNP GOconnect
dpay-web-esim     ─┘     (ten serwis)
```

## Podział domenowy numerów masscollect

Numer rachunku wirtualnego niesie w sobie informację, czyj jest. Dzięki
temu wpłatę da się przypisać do właściciela na podstawie samego wyciągu -
bez zapytania do bazy i nawet wtedy, gdy alokacja została skasowana.

```
NRB (26 cyfr):  CC | 16001462 | 0022 | D | SSSSSSSSSSS
                ^^   ^^^^^^^^   ^^^^   ^   ^^^^^^^^^^^
                |    |          |      |   sekwencja w domenie
                |    |          |      cyfra domeny
                |    |          prefiks nadany przez bank
                |    numer rozliczeniowy banku
                cyfry kontrolne (liczone lokalnie, ISO 7064 mod 97-10)
```

| Cyfra | Domena     | Konsument        | Cykl życia   | Zastosowanie                    |
|-------|------------|------------------|--------------|---------------------------------|
| 1     | `manager`  | dpay-web-manager | jednorazowy  | rachunek per transakcja         |
| 2     | `eid`      | dpay-web-eid     | jednorazowy  | rachunek per przelew weryfikacyjny |
| 3     | `esim`     | dpay-web-esim    | trwały       | rachunek per lokalizacja        |
| 4     | `partners` | rozliczenia      | trwały       | rachunek per partner            |

Przy domyślnej masce zostaje 11 cyfr sekwencji, czyli **100 mld numerów na
domenę**. Podział ustala `database/seeders/BankingSeeder.php`; cyfry domen
są częścią wydanych numerów, więc **nie wolno ich zmieniać po pierwszej
alokacji**.

Dwie zasady wbudowane w alokator:

- **Alokacja jest idempotentna** po `(konsument, owner_type, owner_ref)`.
  Powtórzone żądanie po timeoucie zwraca ten sam numer, a nie kolejny.
- **Numery nie wracają do puli.** Zamknięcie rachunku nie zwalnia numeru.
  Recykling oznaczałby, że spóźniona wpłata trafia do nowego właściciela.

## API

Wszystkie ścieżki pod `/api/v1`. Uwierzytelnienie kluczem konsumenta:
`Authorization: Bearer dprk_...` albo `X-Api-Key`. Klucz nadaje się
w panelu (Konsumenci → Nadaj klucz API) i widać go **tylko raz** - w bazie
leży wyłącznie skrót.

| Metoda | Ścieżka | Opis |
|---|---|---|
| GET | `/bank-accounts` | rachunki rzeczywiste z saldami |
| GET | `/bank-accounts/{iban}` | pojedynczy rachunek |
| GET | `/domains` | domeny dostępne dla konsumenta |
| POST | `/virtual-accounts` | wydanie mikro rachunku (201 nowy, 200 istniejący) |
| GET | `/virtual-accounts` | lista własnych rachunków |
| GET | `/virtual-accounts/{id}` | szczegóły |
| DELETE | `/virtual-accounts/{id}` | zamknięcie dla dalszych wpłat |
| GET | `/virtual-accounts/{id}/payments` | wpłaty na rachunek |
| POST | `/reconciliations` | zestawienie danych konsumenta z bankiem |

### Wydanie mikro rachunku

```http
POST /api/v1/virtual-accounts
Authorization: Bearer dprk_...

{
  "domain": "manager",
  "owner_type": "transaction",
  "owner_ref": "TX-2001",
  "expected_amount_minor": 12999,
  "ttl_minutes": 4320,
  "metadata": {"shop": "dpay"}
}
```

```json
{"data": {
  "iban": "PL04160014620022100000000001",
  "owner_ref": "TX-2001",
  "status": "allocated",
  "expected_amount": "129.99",
  "expires_at": "2026-09-06T12:00:00+00:00"
}}
```

### Reconcile

Konsument przysyła swoją listę za okres, dostaje rozbicie na cztery
kubełki. Sama różnica sald nie wystarcza - dwa błędy potrafią się znieść.

```http
POST /api/v1/reconciliations
{"period_from": "2026-08-01", "period_to": "2026-08-31",
 "items": [{"owner_ref": "TX-1", "amount_minor": 10000}]}
```

Odpowiedź zawiera `matched`, `amount_mismatch`, `missing_in_bank`
(konsument wykazuje, banku nie ma) i `missing_at_consumer` (odwrotnie).
Każdy rozjazd zostaje zapisany i trafia do panelu.

## Komendy i harmonogram

```bash
php artisan bnp:sync-accounts          # salda rachunków (GetAccountBalance)
php artisan bnp:pull-history           # operacje z banku (camt.052, przyrostowo)
php artisan masscollect:match          # dopasowanie wpłat i księgowanie
php artisan masscollect:expire         # oznaczenie wygasłych mikro rachunków
```

Harmonogram jest w `routes/console.php`: pobieranie i dopasowywanie co
5 minut, salda co godzinę. Wymaga działającego `schedule:run`.

## Konfiguracja

Pełna lista w `.env.example`. Minimum do rozmowy z bankiem:

```
BNP_CLIENT_ID=            # identyfikator Klienta GOconnect Biznes
BNP_CERT_PATH=            # certyfikat komunikacyjny (PEM)
BNP_KEY_PATH=             # klucz prywatny (PEM)
BNP_INITIATING_PARTY=dpayrecon   # tylko litery i cyfry - bank odrzuca myślniki
BNP_MASSCOLLECT_BANK_CODE=       # numer rozliczeniowy (8 cyfr)
BNP_MASSCOLLECT_CLIENT_PREFIX=   # prefiks maski nadany przez bank
BNP_MASSCOLLECT_MASTER_IBAN=     # rachunek zbiorczy masscollect
```

Bez certyfikatów aplikacja wstaje normalnie - panel i alokacja numerów
działają, a próba wywołania banku kończy się czytelnym
`GoConnectNotConfiguredException`.

Konwersja certyfikatu z PFX:

```bash
openssl pkcs12 -in klient.pfx -clcerts -nokeys -out klient.crt.pem
openssl pkcs12 -in klient.pfx -nocerts      -out klient.key.pem
```

## Czego kanał bankowy NIE potrafi

Trzy ograniczenia GOconnect, które ukształtowały ten projekt:

1. **Nie ma operacji „lista rachunków”.** Kanał odpowiada wyłącznie na
   pytanie o rachunek, który już znamy. Dlatego rejestrem rachunków jest
   tabela `bank_accounts`, uzupełniana ręcznie w panelu, a `bnp:sync-accounts`
   tylko odświeża salda.
2. **Nie ma webhooków.** Jedyny tryb to odpytywanie, więc pobrania z
   założenia zachodzą na siebie, a odporność siedzi w deduplikacji po
   odcisku palca operacji.
3. **Status `ACSP` nie znaczy „pieniądze wyszły”.** Potwierdzeniem
   wykonania jest pozycja na wyciągu. Ma to znaczenie dopiero przy
   wypłatach, ale przesądza, że domknięcie pętli musi iść przez reconcile,
   a nie przez status zlecenia.

## Księga

Serwis prowadzi **własną** księgę podwójnego zapisu - drugą wobec księgi
w dpay-web-manager. To jest celowe: reconcile ma sens tylko wtedy, gdy obie
strony powstają niezależnie.

Każda pozycja z banku zostaje zaksięgowana, także nierozpoznana. Konto
bankowe w księdze odwzorowuje rzeczywiste saldo w BNP, więc rozjazd między
księgą a `GetAccountBalance` jest prawdziwym alarmem, a nie skutkiem
nieudanego dopasowania. Nierozpoznane wpłaty lądują na koncie przejściowym
`SUSPENSE` i tworzą rozjazd do wyjaśnienia.

Numeracja zapisów (`JE-2026-000001`) idzie z tabeli `ledger_sequences`
z blokadą wiersza - `MAX+1` przy dwóch workerach kolejki wyprodukowałoby
duplikat.

## Czego jeszcze nie ma

- **Wypłaty** (`DomesticTransfer`, `SignDispositions`, pain.002). Biblioteka
  to obsługuje, brakuje po naszej stronie tabeli dyspozycji z `MsgId`
  zapisywanym przed wysyłką i maszyny stanów.
- **Webhooki do konsumentów** - dziś konsument dowiaduje się o wpłacie
  odpytując `/virtual-accounts/{id}/payments`.
- **Rozliczenia BLIK / Visa / MC / PayU** przeniesione na BNP.
- **KSeF** - pobieranie faktur do opłacania jednym kliknięciem.

## Uruchomienie

```bash
composer setup      # instalacja, .env, klucz, migracje
php artisan db:seed # konsumenci, domeny, plan kont
php artisan test
```

Dostęp do panelu ma wyłącznie użytkownik z `users.is_operator = true`.
