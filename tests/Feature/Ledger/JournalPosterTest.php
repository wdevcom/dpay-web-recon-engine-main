<?php

namespace Tests\Feature\Ledger;

use App\Ledger\JournalPoster;
use App\Ledger\Posting;
use App\Ledger\UnbalancedEntryException;
use App\Models\Account;
use App\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Database\Seeders\BankingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalPosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function bank(): Account
    {
        return Account::where('type', Account::TYPE_BANK)->firstOrFail();
    }

    private function masscollect(): Account
    {
        return Account::where('code', 'MASSCOLLECT.MANAGER')->firstOrFail();
    }

    public function test_posts_a_balanced_entry_and_numbers_it_sequentially(): void
    {
        $poster = app(JournalPoster::class);

        $first = $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Wplata 1', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '19166.61')
                ->credit($this->masscollect(), '19166.61')
        );

        $second = $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Wplata 2', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '100.00')
                ->credit($this->masscollect(), '100.00')
        );

        $this->assertSame('JE-2026-000001', $first->entry_no);
        $this->assertSame('JE-2026-000002', $second->entry_no);
        $this->assertSame('19166.61', (string) $first->total);
        $this->assertCount(2, $first->lines);
    }

    public function test_numbering_survives_a_gap_in_the_ledger(): void
    {
        $poster = app(JournalPoster::class);

        $first = $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Wplata', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '10.00')->credit($this->masscollect(), '10.00')
        );

        // Licznik jest niezależny od zawartości księgi, więc usunięcie
        // zapisu nie może spowodować ponownego wydania tego samego numeru.
        $first->lines()->delete();
        $first->delete();

        $next = $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Wplata', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '10.00')->credit($this->masscollect(), '10.00')
        );

        $this->assertSame('JE-2026-000002', $next->entry_no);
    }

    public function test_rejects_an_unbalanced_entry(): void
    {
        $this->expectException(UnbalancedEntryException::class);

        app(JournalPoster::class)->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'zle', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '100.00')
                ->credit($this->masscollect(), '99.99')
        );
    }

    public function test_balance_is_computed_from_the_posting_date_not_the_insert_time(): void
    {
        $poster = app(JournalPoster::class);

        // Import wsteczny: wiersz powstaje dziś, ale operacja jest z lipca.
        $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Lipiec', CarbonImmutable::parse('2026-07-15'))
                ->debit($this->bank(), '500.00')->credit($this->masscollect(), '500.00')
        );
        $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'Sierpien', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '250.50')->credit($this->masscollect(), '250.50')
        );

        $this->assertSame('750.50', (string) $this->bank()->balance());
        $this->assertSame('500.00', (string) $this->bank()->balance(CarbonImmutable::parse('2026-07-31')));
        $this->assertSame('-500.00', (string) $this->masscollect()->balance(CarbonImmutable::parse('2026-07-31')));
    }

    public function test_reversal_creates_a_mirror_entry_and_nets_to_zero(): void
    {
        $poster = app(JournalPoster::class);

        $original = $poster->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'pomylka', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '100.00')->credit($this->masscollect(), '100.00')
        );

        $reversal = $poster->reverse($original, 'zdublowany import');

        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->fresh()->status);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_entry_id);
        $this->assertSame(JournalEntry::SRC_REVERSAL, $reversal->source_type);
        $this->assertSame('0.00', (string) $this->bank()->balance());
    }

    public function test_refuses_to_mix_currencies_in_one_entry(): void
    {
        $eur = Account::create(['code' => 'BANK.EUR', 'name' => 'EUR', 'type' => Account::TYPE_BANK, 'currency' => 'EUR']);

        $this->expectException(\InvalidArgumentException::class);

        app(JournalPoster::class)->post(
            Posting::for(JournalEntry::SRC_MASSCOLLECT, 'mix', CarbonImmutable::parse('2026-08-30'))
                ->debit($this->bank(), '10.00')->credit($eur, '10.00')
        );
    }
}
