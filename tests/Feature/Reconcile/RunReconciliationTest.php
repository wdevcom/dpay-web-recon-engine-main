<?php

namespace Tests\Feature\Reconcile;

use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Models\BankAccount;
use App\Models\Discrepancy;
use App\Models\MasscollectDomain;
use App\Models\Reconciliation;
use App\Models\StatementEntry;
use App\Models\Tenant;
use App\Models\TenantApiKey;
use Database\Seeders\BankingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);

        [, $this->key] = TenantApiKey::issue(Tenant::where('code', 'manager')->firstOrFail(), 'test');
    }

    /** Zaksięgowana wpłata na mikro rachunek konsumenta. */
    private function payment(string $ownerRef, int $amountMinor): void
    {
        $domain = MasscollectDomain::where('code', 'manager')->firstOrFail();
        $virtual = app(VirtualAccountAllocator::class)->allocate($domain, $domain->tenant, 'transaction', $ownerRef);

        StatementEntry::create([
            'bank_account_id'    => BankAccount::query()->firstOrFail()->id,
            'virtual_account_id' => $virtual->id,
            'direction'          => StatementEntry::DIR_CREDIT,
            'amount_minor'       => $amountMinor,
            'currency'           => 'PLN',
            'booking_date'       => '2026-08-30',
            'match_status'       => StatementEntry::MATCH_MATCHED,
            'fingerprint'        => hash('sha256', $ownerRef.$amountMinor),
        ]);
    }

    public function test_splits_the_comparison_into_four_buckets(): void
    {
        $this->payment('TX-OK', 10000);        // zgodne
        $this->payment('TX-DIFF', 9900);       // inna kwota niż u konsumenta
        $this->payment('TX-ONLY-BANK', 5000);  // konsument o tym nie wie

        $response = $this->postJson('/api/v1/reconciliations', [
            'period_from' => '2026-08-01',
            'period_to'   => '2026-08-31',
            'items'       => [
                ['owner_ref' => 'TX-OK', 'amount_minor' => 10000],
                ['owner_ref' => 'TX-DIFF', 'amount_minor' => 10000],
                ['owner_ref' => 'TX-ONLY-CONSUMER', 'amount_minor' => 7700],
            ],
        ], ['Authorization' => 'Bearer '.$this->key]);

        $response->assertStatus(201)
            ->assertJsonPath('status', Reconciliation::STATUS_WITH_DISCREPANCY)
            ->assertJsonPath('matched.0.owner_ref', 'TX-OK')
            ->assertJsonPath('amount_mismatch.0.owner_ref', 'TX-DIFF')
            ->assertJsonPath('amount_mismatch.0.difference', -100)
            ->assertJsonPath('missing_in_bank.0.owner_ref', 'TX-ONLY-CONSUMER')
            ->assertJsonPath('missing_at_consumer.0.owner_ref', 'TX-ONLY-BANK');

        // 249.00 u nas kontra 277.00 u konsumenta.
        $this->assertSame('249.00', $response->json('totals.bank'));
        $this->assertSame('277.00', $response->json('totals.counterparty'));
        $this->assertSame('-28.00', $response->json('totals.difference'));

        $this->assertSame(3, Discrepancy::count());
        $this->assertSame(1, Discrepancy::where('type', Discrepancy::TYPE_MISSING_IN_BANK)->count());
        $this->assertSame(1, Discrepancy::where('type', Discrepancy::TYPE_MISSING_AT_CONSUMER)->count());
    }

    public function test_marks_a_clean_period_as_balanced(): void
    {
        $this->payment('TX-1', 10000);
        $this->payment('TX-2', 2500);

        $response = $this->postJson('/api/v1/reconciliations', [
            'period_from' => '2026-08-01',
            'period_to'   => '2026-08-31',
            'items'       => [
                ['owner_ref' => 'TX-1', 'amount_minor' => 10000],
                ['owner_ref' => 'TX-2', 'amount_minor' => 2500],
            ],
        ], ['Authorization' => 'Bearer '.$this->key]);

        $response->assertStatus(201)->assertJsonPath('status', Reconciliation::STATUS_BALANCED);

        $this->assertSame(0, Discrepancy::count());
        $this->assertSame('0.00', $response->json('totals.difference'));
        $this->assertMatchesRegularExpression('/^REC-2026-\d{6}$/', $response->json('group_no'));
    }

    public function test_ignores_payments_outside_the_period(): void
    {
        $this->payment('TX-STARY', 10000);
        StatementEntry::query()->update(['booking_date' => '2026-07-15']);

        $response = $this->postJson('/api/v1/reconciliations', [
            'period_from' => '2026-08-01',
            'period_to'   => '2026-08-31',
            'items'       => [],
        ], ['Authorization' => 'Bearer '.$this->key]);

        $response->assertStatus(201)->assertJsonPath('totals.bank', '0.00');
    }
}
