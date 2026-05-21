<?php

namespace App\Recon\Pipelines;

use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Parsers\StatementParser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates: select parser → parse → persist source_rows.
 * Idempotent in the sense that the source_documents.file_hash unique index
 * blocks re-uploads of the same file; within a single ingest run, rows
 * are inserted in a transaction with progress-counting.
 */
class IngestSourceDocument
{
    /** @var array<int, StatementParser> */
    private array $parsers;

    public function __construct(private Container $container)
    {
        // Lazily resolved via registry in ReconServiceProvider.
        $this->parsers = $container->tagged('recon.parser')->toArray() ?: [];
    }

    public function handle(SourceDocument $doc): SourceDocument
    {
        $doc->update(['parse_status' => SourceDocument::STATUS_PARSING]);

        $disk = Storage::disk(config('recon.storage_disk', 'local'));
        $absolute = $disk->path($doc->storage_path);

        try {
            $parser = $this->pickParser($doc, $absolute);
            $count = 0;
            DB::transaction(function () use ($parser, $doc, $absolute, &$count) {
                foreach ($parser->parse($doc, $absolute) as $parsed) {
                    SourceRow::create([
                        'source_document_id' => $doc->id,
                        'row_no'             => $parsed->rowNo,
                        'raw_payload'        => $parsed->raw,
                        'normalized'         => $parsed->toNormalizedArray(),
                        'external_id'        => $parsed->externalId,
                        'value_date'         => $parsed->valueDate,
                        'posted_at'          => $parsed->postedAt,
                        'gross_amount'       => (string) $parsed->grossAmount,
                        'net_amount'         => $parsed->netAmount ? (string) $parsed->netAmount : null,
                        'commission_amount'  => $parsed->commissionAmount ? (string) $parsed->commissionAmount : null,
                        'currency'           => $parsed->currency,
                        'row_type'           => $parsed->rowType,
                        'match_status'       => SourceRow::STATUS_UNMATCHED,
                    ]);
                    $count++;
                }
            });

            $doc->update([
                'parse_status' => SourceDocument::STATUS_PARSED,
                'rows_count'   => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error('Source document ingest failed', [
                'document_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);
            $doc->update([
                'parse_status' => SourceDocument::STATUS_FAILED,
                'parse_errors' => [
                    'message' => $e->getMessage(),
                    'class'   => $e::class,
                ],
            ]);
            throw $e;
        }

        return $doc->fresh();
    }

    private function pickParser(SourceDocument $doc, string $absolutePath): StatementParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->format() === $doc->format && $parser->supports($doc, $absolutePath)) {
                return $parser;
            }
        }
        // Fallback: try every parser's `supports`, by content.
        foreach ($this->parsers as $parser) {
            if ($parser->supports($doc, $absolutePath)) {
                return $parser;
            }
        }
        throw new \RuntimeException("No parser found for format '{$doc->format}'.");
    }
}
