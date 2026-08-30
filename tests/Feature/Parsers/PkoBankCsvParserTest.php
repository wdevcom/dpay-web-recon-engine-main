<?php

namespace Tests\Feature\Parsers;

use App\Models\Provider;
use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Parsers\PkoBankCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PkoBankCsvParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_all_six_sibs_inflows_from_fixture(): void
    {
        $this->seed(\Database\Seeders\ReconCoreSeeder::class);
        $provider = Provider::where('code', 'bank_pko')->firstOrFail();
        $doc = SourceDocument::create([
            'provider_id' => $provider->id,
            'format'      => 'csv_pko',
            'filename'    => 'pko_sibs.csv',
            'file_hash'   => hash_file('sha256', base_path('tests/Fixtures/pko_sibs.csv')),
            'received_via'=> 'upload',
        ]);

        $rows = iterator_to_array((new PkoBankCsvParser)->parse($doc, base_path('tests/Fixtures/pko_sibs.csv')));

        $this->assertCount(6, $rows);
        $first = $rows[0];
        $this->assertSame(SourceRow::TYPE_BANK_CREDIT, $first->rowType);
        $this->assertSame('19166.61', (string) $first->grossAmount);
        $this->assertSame('PLN', $first->currency);
        $this->assertSame('SIBS PAGAMENTOS S.A.|ESTRADA DE ALFRAGIDE 67 2610-008 AMADORA', $first->extra['counterparty_name']);
        $this->assertSame('Bramka platnicza rozliczenie', $first->extra['title']);

        // sum of all 6 == 156214.19 per file header
        $sum = array_reduce($rows, fn ($acc, $r) => $acc->plus($r->grossAmount), \Brick\Math\BigDecimal::zero());
        $this->assertSame('156214.19', (string) $sum);
    }

    public function test_extracts_psp_payout_id_as_external_id_for_blik(): void
    {
        $this->seed(\Database\Seeders\ReconCoreSeeder::class);
        $provider = Provider::where('code', 'bank_pko')->firstOrFail();
        $doc = SourceDocument::create([
            'provider_id' => $provider->id,
            'format'      => 'csv_pko',
            'filename'    => 'pko_blik.csv',
            'file_hash'   => hash_file('sha256', base_path('tests/Fixtures/pko_blik.csv')),
            'received_via'=> 'upload',
        ]);

        $rows = iterator_to_array((new PkoBankCsvParser)->parse($doc, base_path('tests/Fixtures/pko_blik.csv')));
        $this->assertCount(6, $rows);

        // First row title is "PSP-000002919" -> should land in externalId
        $this->assertSame('PSP-000002919', $rows[0]->externalId);
        // Row with "PSP 000002915" (no dash) — still captured as title verbatim
        $this->assertSame('PSP 000002915', $rows[4]->externalId);
    }
}
