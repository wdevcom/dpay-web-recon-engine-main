<?php

namespace App\Console\Commands;

use App\Banking\Reconcile\MatchStatementEntries;
use App\Models\BankAccount;
use Illuminate\Console\Command;

class MatchStatementEntriesCommand extends Command
{
    protected $signature = 'masscollect:match {--account= : IBAN rachunku} {--limit=500}';

    protected $description = 'Dopasowuje pozycje z banku do mikro rachunków i księguje je.';

    public function handle(MatchStatementEntries $matcher): int
    {
        $account = $this->option('account')
            ? BankAccount::where('iban', $this->option('account'))->first()
            : null;

        $stats = $matcher->handle($account, (int) $this->option('limit'));

        $this->info(sprintf(
            'Dopasowane: %d, na koncie przejściowym: %d, pominięte: %d',
            $stats['matched'],
            $stats['suspense'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
