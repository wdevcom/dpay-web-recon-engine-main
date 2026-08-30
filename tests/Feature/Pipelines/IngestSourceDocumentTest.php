<?php

namespace Tests\Feature\Pipelines;

use App\Models\Provider;
use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Pipelines\IngestSourceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IngestSourceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingests_pko_csv_end_to_end(): void
    {
        $this->seed(\Database\Seeders\ReconCoreSeeder::class);
        Storage::fake(config('recon.storage_disk', 'local'));

        $payload = file_get_contents(base_path('tests/Fixtures/pko_sibs.csv'));
        Storage::put('source_documents/pko_sibs.csv', $payload);

        $provider = Provider::where('code', 'bank_pko')->firstOrFail();
        $doc = SourceDocument::create([
            'provider_id'   => $provider->id,
            'format'        => 'csv_pko',
            'filename'      => 'pko_sibs.csv',
            'storage_path'  => 'source_documents/pko_sibs.csv',
            'file_hash'     => hash('sha256', $payload),
            'received_via'  => 'upload',
            'parse_status'  => SourceDocument::STATUS_PENDING,
        ]);

        app(IngestSourceDocument::class)->handle($doc);

        $doc = $doc->fresh();
        $this->assertSame(SourceDocument::STATUS_PARSED, $doc->parse_status);
        $this->assertSame(6, $doc->rows_count);
        $this->assertSame(6, SourceRow::query()->where('source_document_id', $doc->id)->count());

        $firstRow = SourceRow::query()->where('source_document_id', $doc->id)->orderBy('row_no')->first();
        $this->assertSame(SourceRow::TYPE_BANK_CREDIT, $firstRow->row_type);
        $this->assertSame('19166.61', (string) $firstRow->gross_amount);
    }
}
