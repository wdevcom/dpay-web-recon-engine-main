<?php

namespace Tests\Feature\Ledger;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Recon\Ledger\JournalPoster;
use App\Recon\Ledger\Posting;
use App\Recon\Ledger\UnbalancedEntryException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalPosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ReconCoreSeeder::class);
    }

    public function test_posts_a_balanced_entry_and_assigns_sequential_entry_no(): void
    {
        $bank = Account::where('code', 'BANK.PKO.96253000082060107214200001')->firstOrFail();
        $clearing = Account::where('code', 'CLEARING.SIBS')->firstOrFail();

        $poster = app(JournalPoster::class);

        $entry1 = $poster->post(
            Posting::for(JournalEntry::SRC_BANK, 'SIBS payout', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '19166.61')
                ->credit($clearing, '19166.61')
        );

        $entry2 = $poster->post(
            Posting::for(JournalEntry::SRC_BANK, 'Another SIBS payout', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '100.00')
                ->credit($clearing, '100.00')
        );

        $this->assertSame('JE-2026-000001', $entry1->entry_no);
        $this->assertSame('JE-2026-000002', $entry2->entry_no);
        $this->assertSame('19166.61', (string) $entry1->total);
        $this->assertCount(2, $entry1->lines);
    }

    public function test_rejects_unbalanced_entry(): void
    {
        $bank = Account::where('code', 'BANK.PKO.96253000082060107214200001')->firstOrFail();
        $clearing = Account::where('code', 'CLEARING.SIBS')->firstOrFail();

        $this->expectException(UnbalancedEntryException::class);

        app(JournalPoster::class)->post(
            Posting::for(JournalEntry::SRC_BANK, 'wrong', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '100.00')
                ->credit($clearing, '99.99')
        );
    }

    public function test_account_balance_aggregates_lines(): void
    {
        $bank = Account::where('code', 'BANK.PKO.96253000082060107214200001')->firstOrFail();
        $clearing = Account::where('code', 'CLEARING.SIBS')->firstOrFail();
        $poster = app(JournalPoster::class);

        $poster->post(
            Posting::for(JournalEntry::SRC_BANK, 'in', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '500.00')->credit($clearing, '500.00')
        );
        $poster->post(
            Posting::for(JournalEntry::SRC_BANK, 'in', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '250.50')->credit($clearing, '250.50')
        );

        $this->assertSame('750.50', (string) $bank->balance());
        $this->assertSame('-750.50', (string) $clearing->balance());
    }

    public function test_reverse_creates_mirror_entry(): void
    {
        $bank = Account::where('code', 'BANK.PKO.96253000082060107214200001')->firstOrFail();
        $clearing = Account::where('code', 'CLEARING.SIBS')->firstOrFail();
        $poster = app(JournalPoster::class);

        $original = $poster->post(
            Posting::for(JournalEntry::SRC_BANK, 'oops', CarbonImmutable::parse('2026-05-21'))
                ->debit($bank, '100.00')->credit($clearing, '100.00')
        );

        $reversal = $poster->reverse($original, 'duplicate import');

        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->fresh()->status);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_entry_id);
        $this->assertSame('0.00', (string) $bank->balance()); // net zero
    }
}
