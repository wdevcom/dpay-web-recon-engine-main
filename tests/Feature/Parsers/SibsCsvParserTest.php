<?php

namespace Tests\Feature\Parsers;

use App\Models\Provider;
use App\Models\SourceDocument;
use App\Models\SourceRow;
use App\Recon\Parsers\SibsCsvParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SibsCsvParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_sales_and_refund_with_commission(): void
    {
        $this->seed(\Database\Seeders\ReconCoreSeeder::class);
        $provider = Provider::where('code', 'sibs')->firstOrFail();
        $doc = SourceDocument::create([
            'provider_id' => $provider->id,
            'format'      => 'sibs_csv',
            'filename'    => 'sibs.csv',
            'file_hash'   => hash_file('sha256', base_path('tests/Fixtures/sibs.csv')),
            'received_via'=> 'upload',
        ]);

        $rows = iterator_to_array((new SibsCsvParser)->parse($doc, base_path('tests/Fixtures/sibs.csv')));
        $this->assertCount(3, $rows);

        // Row 1: SALES 1.00 net 0.99 fee 0.01 PLN (currency 985)
        $sale = $rows[0];
        $this->assertSame(SourceRow::TYPE_SALE, $sale->rowType);
        $this->assertSame('1.00', (string) $sale->grossAmount);
        $this->assertSame('0.99', (string) $sale->netAmount);
        $this->assertSame('0.01', (string) $sale->commissionAmount);
        $this->assertSame('PLN', $sale->currency);
        $this->assertSame('STGB00500806VYk7KaEc1SrqrkpV11FU', $sale->externalId);
        $this->assertSame('0.99', $sale->extra['payout_amount']);
        $this->assertSame('PL96253000082060107214200001', $sale->extra['payout_account']);

        // Row 2: REFUND -1.00, original_transaction_id points at row 3
        $refund = $rows[1];
        $this->assertSame(SourceRow::TYPE_REFUND, $refund->rowType);
        $this->assertSame('-1.00', (string) $refund->grossAmount);
        $this->assertSame('STGB00500806MUfqPcNwPzZ8R606K929', $refund->extra['original_transaction_id']);
    }
}
