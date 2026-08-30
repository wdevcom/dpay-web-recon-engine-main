<?php

namespace App\Recon\Parsers;

use App\Models\SourceDocument;

/**
 * Strategy interface for every supported file format.
 * Implementations live in app/Recon/Parsers/* and are registered in
 * App\Providers\ReconServiceProvider::parsers().
 */
interface StatementParser
{
    /** Format code from config/recon.php providers[*].format. */
    public function format(): string;

    /** Cheap inspection before reading the whole file (e.g. by header). */
    public function supports(SourceDocument $doc, string $absolutePath): bool;

    /**
     * Parse the file at $absolutePath. Yields ParsedRow instances.
     *
     * @return iterable<ParsedRow>
     */
    public function parse(SourceDocument $doc, string $absolutePath): iterable;
}
