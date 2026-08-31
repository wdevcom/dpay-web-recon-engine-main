<?php

namespace Tests\Feature\Reconcile;

use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Banking\Reconcile\MatchStatementEntries;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Discrepancy;
use App\Models\MasscollectDomain;
use App\Models\StatementEntry;
use App\Models\VirtualAccount;
use Carbon\CarbonImmutable;
use Database\Seeders\BankingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchStatementEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function entry(array $overrides = []): StatementEntry
    {
        return StatementEntry::create(array_merge([
            'bank_account_id' => BankAccount::query()->firstOrFail()->id,
            'direction'       => StatementEntry::DIR_CREDIT,
            'amount_minor'    => 12999,
            'currency'        => 'PLN',
            'booking_date'    => '2026-08-30',
            'value_date'      => '2026-08-30',
            'counterparty_name' => 'Anna Nowak',
            'match_status'    => StatementEntry::MATCH_UNMATCHED,
            'fingerprint'     => hash('sha256', uniqid('', true)),
        ], $overrides));
    }

    private function allocate(string $domainCode, string $ownerRef, array $options = []): VirtualAccount
    {
        $domain = MasscollectDomain::where('code', $domainCode)->firstOrFail();

        return app(VirtualAccountAllocator::class)->allocate($domain, $domain->tenant, 'transaction', $ownerRef, $options);
    }

    public function test_books_a_recognised_payment_against_the_domain(): void
    {
        $virtual = $this->allocate('manager', 'TX-1', ['expected_amount_minor' => 12999]);
        $entry = $this->entry(['detected_virtual_account' => $virtual->nrb]);

        $stats = app(MatchStatementEntries::class)->handle();

        $this->assertSame(['matched' => 1, 'suspense' => 0, 'skipped' => 0], $stats);

        $entry = $entry->fresh();
        $this->assertSame(StatementEntry::MATCH_MATCHED, $entry->match_status);
        $this->assertSame($virtual->id, $entry->virtual_account_id);
        $this->assertNotNull($entry->journal_entry_id);

        $virtual = $virtual->fresh();
        $this->assertSame(12999, $virtual->received_amount_minor);
        $this->assertSame(1, $virtual->payments_count);
        $this->assertSame(VirtualAccount::STATUS_ACTIVE, $virtual->status);

        // Bank rośnie po stronie WN, zobowiązanie wobec domeny po stronie MA.
        $bank = Account::where('type', Account::TYPE_BANK)->firstOrFail();
        $domain = Account::where('code', 'MASSCOLLECT.MANAGER')->firstOrFail();
        $this->assertSame('129.99', (string) $bank->balance());
        $this->assertSame('-129.99', (string) $domain->balance());

        $this->assertSame(0, Discrepancy::count());
    }

    public function test_payment_on_an_unknown_number_lands_on_suspense_with_a_discrepancy(): void
    {
        // Numer z naszej maski, ale nigdy nie wydany - to poważniejszy
        // przypadek niż zwykła wpłata bez identyfikacji.
        $orphan = app(\App\Banking\Masscollect\AccountNumberMask::class)->build('1', 987654321);
        $this->entry(['detected_virtual_account' => $orphan]);

        $stats = app(MatchStatementEntries::class)->handle();

        $this->assertSame(1, $stats['suspense']);

        $entry = StatementEntry::firstOrFail();
        $this->assertSame(StatementEntry::MATCH_SUSPENSE, $entry->match_status);
        $this->assertNotNull($entry->journal_entry_id);

        $discrepancy = Discrepancy::firstOrFail();
        $this->assertSame(Discrepancy::TYPE_UNIDENTIFIED_PAYMENT, $discrepancy->type);
        $this->assertSame('critical', $discrepancy->severity);

        // Pieniądz jest realny, więc konto bankowe w księdze i tak rośnie -
        // przeciwwagą jest konto przejściowe.
        $this->assertSame('129.99', (string) Account::where('type', Account::TYPE_BANK)->firstOrFail()->balance());
        $this->assertSame('-129.99', (string) Account::where('code', 'SUSPENSE')->firstOrFail()->balance());
    }

    public function test_payment_without_any_recognised_number_goes_to_suspense(): void
    {
        $this->entry(['detected_virtual_account' => null, 'remittance_text' => 'Za fakture 12/2026']);

        app(MatchStatementEntries::class)->handle();

        $this->assertSame(StatementEntry::MATCH_SUSPENSE, StatementEntry::firstOrFail()->match_status);
        $this->assertSame('warning', Discrepancy::firstOrFail()->severity);
    }

    public function test_payment_after_expiry_is_booked_but_flagged(): void
    {
        $virtual = $this->allocate('manager', 'TX-OLD');
        $virtual->update(['expires_at' => CarbonImmutable::now()->subDay(), 'status' => VirtualAccount::STATUS_EXPIRED]);

        $this->entry(['detected_virtual_account' => $virtual->nrb]);

        app(MatchStatementEntries::class)->handle();

        $entry = StatementEntry::firstOrFail();
        $this->assertSame(StatementEntry::MATCH_SUSPENSE, $entry->match_status);
        $this->assertSame($virtual->id, $entry->virtual_account_id);

        $discrepancy = Discrepancy::firstOrFail();
        $this->assertSame(Discrepancy::TYPE_PAYMENT_AFTER_EXPIRY, $discrepancy->type);

        // Kwota nie została dopisana do rachunku, bo nie jest jego wpłatą.
        $this->assertSame(0, $virtual->fresh()->received_amount_minor);
    }

    public function test_wrong_amount_on_a_one_time_account_raises_a_mismatch(): void
    {
        $virtual = $this->allocate('manager', 'TX-PART', ['expected_amount_minor' => 20000]);
        $this->entry(['detected_virtual_account' => $virtual->nrb, 'amount_minor' => 12999]);

        app(MatchStatementEntries::class)->handle();

        $discrepancy = Discrepancy::firstOrFail();
        $this->assertSame(Discrepancy::TYPE_AMOUNT_MISMATCH, $discrepancy->type);
        $this->assertSame(12999 - 20000, $discrepancy->amount_minor);

        // Mimo rozjazdu pieniądz jest zaksięgowany po stronie właściwej domeny.
        $this->assertSame(StatementEntry::MATCH_MATCHED, StatementEntry::firstOrFail()->match_status);
    }

    public function test_debits_are_booked_against_suspense(): void
    {
        $this->entry(['direction' => StatementEntry::DIR_DEBIT, 'amount_minor' => 5000, 'detected_virtual_account' => null]);

        app(MatchStatementEntries::class)->handle();

        // Saldo księgi ma odwzorowywać saldo w banku, także dla obciążeń.
        $this->assertSame('-50.00', (string) Account::where('type', Account::TYPE_BANK)->firstOrFail()->balance());
        $this->assertSame('50.00', (string) Account::where('code', 'SUSPENSE')->firstOrFail()->balance());
    }

    public function test_matching_is_not_repeated_for_already_processed_entries(): void
    {
        $virtual = $this->allocate('manager', 'TX-ONCE');
        $this->entry(['detected_virtual_account' => $virtual->nrb]);

        app(MatchStatementEntries::class)->handle();
        $second = app(MatchStatementEntries::class)->handle();

        $this->assertSame(['matched' => 0, 'suspense' => 0, 'skipped' => 0], $second);
        $this->assertSame(1, \App\Models\JournalEntry::count());
        $this->assertSame(12999, $virtual->fresh()->received_amount_minor);
    }
}
