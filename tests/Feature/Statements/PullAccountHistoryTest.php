<?php

namespace Tests\Feature\Statements;

use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Banking\Statements\PullAccountHistory;
use App\Models\BankAccount;
use App\Models\BnpOperation;
use App\Models\MasscollectDomain;
use App\Models\StatementEntry;
use Database\Seeders\BankingSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGoConnect;
use Tests\TestCase;

/**
 * Test idzie przez prawdziwą bibliotekę bankową - atrapą jest dopiero
 * warstwa HTTP. Dzięki temu sprawdzamy realne parsowanie camt.052, a nie
 * własne wyobrażenie o tym, co bank przysyła.
 */
class PullAccountHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
    }

    private function account(): BankAccount
    {
        return BankAccount::query()->firstOrFail();
    }

    public function test_parses_camt_entries_into_statement_entries(): void
    {
        $account = $this->account();
        $domain = MasscollectDomain::where('code', 'manager')->firstOrFail();
        $virtual = app(VirtualAccountAllocator::class)->allocate($domain, $domain->tenant, 'transaction', 'TX-77');

        FakeGoConnect::bind([
            FakeGoConnect::accountReport($account->iban, [
                ['amount' => '129.99', 'number' => '1', 'credited' => $virtual->iban, 'remittance' => 'Zamowienie 77', 'debtor' => 'Anna Nowak'],
                ['amount' => '10.00', 'number' => '2', 'direction' => 'DBIT', 'remittance' => 'Oplata bankowa'],
            ]),
        ]);

        $statement = app(PullAccountHistory::class)->handle(
            $account,
            new DateTimeImmutable('2026-08-29'),
            new DateTimeImmutable('2026-08-30'),
        );

        $this->assertSame(2, $statement->entries_count);
        $this->assertSame(2, $statement->new_entries_count);

        $credit = StatementEntry::where('direction', StatementEntry::DIR_CREDIT)->firstOrFail();
        $this->assertSame(12999, $credit->amount_minor);
        $this->assertSame('PLN', $credit->currency);
        $this->assertSame('Anna Nowak', $credit->counterparty_name);
        $this->assertSame('Zamowienie 77', $credit->remittance_text);

        // Rachunek uznany to nasz mikro rachunek - wykryty przed zapisem.
        $this->assertSame($virtual->nrb, $credit->detected_virtual_account);

        $debit = StatementEntry::where('direction', StatementEntry::DIR_DEBIT)->firstOrFail();
        $this->assertSame(1000, $debit->amount_minor);
        $this->assertNull($debit->detected_virtual_account);
    }

    public function test_detects_the_virtual_account_from_the_transfer_title(): void
    {
        $account = $this->account();
        $domain = MasscollectDomain::where('code', 'esim')->firstOrFail();
        $virtual = app(VirtualAccountAllocator::class)->allocate($domain, $domain->tenant, 'location', 'LOC-9');

        FakeGoConnect::bind([
            FakeGoConnect::accountReport($account->iban, [
                // Numer w tytule, łamany spacjami - tak wygląda w praktyce.
                ['amount' => '50.00', 'number' => '1', 'remittance' => 'Doladowanie '.trim(chunk_split($virtual->nrb, 4, ' '))],
            ]),
        ]);

        app(PullAccountHistory::class)->handle($account, new DateTimeImmutable('2026-08-30'), new DateTimeImmutable('2026-08-30'));

        $this->assertSame($virtual->nrb, StatementEntry::firstOrFail()->detected_virtual_account);
    }

    public function test_refetching_the_same_period_does_not_duplicate_entries(): void
    {
        $account = $this->account();

        $report = FakeGoConnect::accountReport($account->iban, [
            ['amount' => '11.00', 'number' => '1'],
            ['amount' => '22.00', 'number' => '2'],
        ]);

        FakeGoConnect::bind([$report, $report]);

        $from = new DateTimeImmutable('2026-08-30');

        $first = app(PullAccountHistory::class)->handle($account, $from, $from);
        $second = app(PullAccountHistory::class)->handle($account->fresh(), $from, $from);

        // Pobrania z założenia zachodzą na siebie - druga porcja nie może
        // dołożyć ani jednego wiersza.
        $this->assertSame(2, $first->new_entries_count);
        $this->assertSame(0, $second->new_entries_count);
        $this->assertSame(2, StatementEntry::count());
    }

    public function test_every_bank_call_is_logged_with_its_message_id(): void
    {
        $account = $this->account();

        FakeGoConnect::bind([FakeGoConnect::accountReport($account->iban, [])]);

        app(PullAccountHistory::class)->handle($account, new DateTimeImmutable('2026-08-30'), new DateTimeImmutable('2026-08-30'));

        $operation = BnpOperation::firstOrFail();

        $this->assertSame('GetAccountReport', $operation->operation);
        $this->assertSame(BnpOperation::STATUS_OK, $operation->status);
        $this->assertSame($account->iban, $operation->account_iban);
        $this->assertNotEmpty($operation->message_id);
    }
}
