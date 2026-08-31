<?php

namespace App\Providers;

use App\Banking\GoConnect\GoConnectFactory;
use App\Banking\GoConnect\GoConnectGateway;
use App\Banking\Masscollect\AccountNumberMask;
use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Banking\Reconcile\MatchStatementEntries;
use App\Banking\Reconcile\VirtualAccountDetector;
use App\Banking\Statements\PullAccountHistory;
use App\Banking\Statements\StatementEntryMapper;
use App\Banking\Statements\SyncBankAccounts;
use App\Ledger\JournalPoster;
use App\Ledger\LedgerAccounts;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class BankingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Maska jest jedna dla całej instalacji - to umowa z bankiem,
        // nie parametr wywołania.
        $this->app->singleton(AccountNumberMask::class, fn () => AccountNumberMask::fromConfig());

        $this->app->singleton(VirtualAccountDetector::class);
        $this->app->singleton(VirtualAccountAllocator::class);
        $this->app->singleton(StatementEntryMapper::class);
        $this->app->singleton(JournalPoster::class);
        $this->app->singleton(LedgerAccounts::class);

        $this->app->singleton(GoConnectFactory::class, fn ($app) => new GoConnectFactory($app->make(LoggerInterface::class)));
        $this->app->singleton(GoConnectGateway::class);

        $this->app->singleton(SyncBankAccounts::class);
        $this->app->singleton(PullAccountHistory::class);
        $this->app->singleton(MatchStatementEntries::class);
    }
}
