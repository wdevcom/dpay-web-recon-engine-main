<?php

namespace App\Providers;

use App\Recon\Ledger\JournalPoster;
use App\Recon\Parsers\PkoBankCsvParser;
use App\Recon\Parsers\SibsCsvParser;
use App\Recon\Parsers\StatementParser;
use App\Recon\Pipelines\IngestSourceDocument;
use Illuminate\Support\ServiceProvider;

class ReconServiceProvider extends ServiceProvider
{
    /**
     * Every parser is tagged with `recon.parser` so the ingest pipeline can
     * discover them via the container. Add new ones here.
     */
    private const PARSERS = [
        PkoBankCsvParser::class,
        SibsCsvParser::class,
    ];

    public function register(): void
    {
        foreach (self::PARSERS as $class) {
            $this->app->singleton($class);
        }
        $this->app->tag(self::PARSERS, 'recon.parser');

        $this->app->bind(StatementParser::class, PkoBankCsvParser::class); // dummy default

        $this->app->singleton(JournalPoster::class);
        $this->app->bind(IngestSourceDocument::class, function ($app) {
            return new IngestSourceDocument($app);
        });
    }

    public function boot(): void
    {
        //
    }
}
