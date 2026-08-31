<?php

namespace App\Console\Commands;

use App\Banking\Statements\SyncBankAccounts;
use App\Models\BankAccount;
use App\Support\Money;
use Illuminate\Console\Command;

class SyncBankAccountsCommand extends Command
{
    protected $signature = 'bnp:sync-accounts {--account= : IBAN pojedynczego rachunku}';

    protected $description = 'Odświeża salda rachunków BNP (GetAccountBalance per rachunek z rejestru).';

    public function handle(SyncBankAccounts $sync): int
    {
        $only = null;

        if ($iban = $this->option('account')) {
            $only = BankAccount::where('iban', $iban)->first();

            if ($only === null) {
                $this->error(sprintf('Rachunek %s nie istnieje w rejestrze. Dodaj go w panelu - GOconnect nie zwraca listy rachunków.', $iban));

                return self::FAILURE;
            }
        }

        $results = $sync->handle($only);

        $this->table(
            ['IBAN', 'Nazwa', 'Dostępne', 'Księgowe', 'Błąd'],
            $results->map(fn (BankAccount $a) => [
                $a->iban,
                $a->name,
                $a->available_balance_minor === null ? '-' : Money::formatMinorUnits($a->available_balance_minor),
                $a->booked_balance_minor === null ? '-' : Money::formatMinorUnits($a->booked_balance_minor),
                $a->sync_error ?? '',
            ])->all(),
        );

        return $results->contains(fn (BankAccount $a) => $a->sync_error !== null) ? self::FAILURE : self::SUCCESS;
    }
}
