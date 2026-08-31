<?php

namespace App\Console\Commands;

use App\Banking\Statements\PullAccountHistory;
use App\Models\BankAccount;
use DateTimeImmutable;
use Illuminate\Console\Command;

class PullAccountHistoryCommand extends Command
{
    protected $signature = 'bnp:pull-history
        {--account= : IBAN pojedynczego rachunku}
        {--from= : Data od (Y-m-d), wymusza pełne pobranie zamiast przyrostu}
        {--to= : Data do (Y-m-d)}';

    protected $description = 'Pobiera operacje z BNP do statement_entries (przyrostowo, jeśli to możliwe).';

    public function handle(PullAccountHistory $puller): int
    {
        $accounts = $this->option('account')
            ? BankAccount::where('iban', $this->option('account'))->get()
            : BankAccount::where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            $this->error('Brak rachunków do pobrania.');

            return self::FAILURE;
        }

        $from = $this->option('from') ? new DateTimeImmutable($this->option('from')) : null;
        $to = $this->option('to') ? new DateTimeImmutable($this->option('to')) : null;

        foreach ($accounts as $account) {
            $statement = $puller->handle($account, $from, $to);

            $this->info(sprintf(
                '%s: %s %s..%s - %d pozycji, %d nowych',
                $account->iban,
                $statement->source,
                $statement->period_from->format('Y-m-d'),
                $statement->period_to->format('Y-m-d'),
                $statement->entries_count,
                $statement->new_entries_count,
            ));
        }

        return self::SUCCESS;
    }
}
