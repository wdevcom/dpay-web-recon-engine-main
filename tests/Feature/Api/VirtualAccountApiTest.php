<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\MasscollectDomain;
use App\Models\StatementEntry;
use App\Models\Tenant;
use App\Models\TenantApiKey;
use App\Models\VirtualAccount;
use Database\Seeders\BankingSeeder;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\BankAccountNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);

        [, $this->key] = TenantApiKey::issue(Tenant::where('code', 'manager')->firstOrFail(), 'test');
    }

    private function headers(?string $key = null): array
    {
        return ['Authorization' => 'Bearer '.($key ?? $this->key)];
    }

    public function test_rejects_requests_without_a_valid_key(): void
    {
        $this->postJson('/api/v1/virtual-accounts')->assertStatus(401);
        $this->getJson('/api/v1/bank-accounts', $this->headers('nieistniejacy'))->assertStatus(401);
    }

    public function test_allocates_a_virtual_account(): void
    {
        $response = $this->postJson('/api/v1/virtual-accounts', [
            'domain'                => 'manager',
            'owner_type'            => 'transaction',
            'owner_ref'             => 'TX-2001',
            'expected_amount_minor' => 12999,
            'label'                 => 'Zamowienie 2001',
            'metadata'              => ['shop' => 'dpay'],
        ], $this->headers());

        $response->assertStatus(201)
            ->assertJsonPath('data.owner_ref', 'TX-2001')
            ->assertJsonPath('data.domain', 'manager')
            ->assertJsonPath('data.status', VirtualAccount::STATUS_ALLOCATED)
            ->assertJsonPath('data.expected_amount', '129.99');

        $iban = $response->json('data.iban');

        // Numer wydany przez API musi przejść walidację biblioteki bankowej.
        $this->assertSame($iban, BankAccountNumber::fromString($iban)->iban());
    }

    public function test_repeated_allocation_returns_the_same_account_with_200(): void
    {
        $payload = ['domain' => 'manager', 'owner_type' => 'transaction', 'owner_ref' => 'TX-DUP'];

        $first = $this->postJson('/api/v1/virtual-accounts', $payload, $this->headers())->assertStatus(201);
        $second = $this->postJson('/api/v1/virtual-accounts', $payload, $this->headers())->assertStatus(200);

        $this->assertSame($first->json('data.iban'), $second->json('data.iban'));
        $this->assertSame(1, VirtualAccount::count());
    }

    public function test_consumer_cannot_allocate_from_another_consumers_domain(): void
    {
        $this->postJson('/api/v1/virtual-accounts', [
            'domain'     => 'esim',
            'owner_type' => 'location',
            'owner_ref'  => 'LOC-1',
        ], $this->headers())->assertStatus(403);
    }

    public function test_lists_only_own_accounts(): void
    {
        $this->postJson('/api/v1/virtual-accounts', ['domain' => 'manager', 'owner_type' => 'transaction', 'owner_ref' => 'TX-A'], $this->headers());

        $esim = MasscollectDomain::where('code', 'esim')->firstOrFail();
        app(\App\Banking\Masscollect\VirtualAccountAllocator::class)
            ->allocate($esim, $esim->tenant, 'location', 'LOC-OBCY');

        $response = $this->getJson('/api/v1/virtual-accounts', $this->headers())->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('TX-A', $response->json('data.0.owner_ref'));
    }

    public function test_returns_payments_credited_to_an_account(): void
    {
        $created = $this->postJson('/api/v1/virtual-accounts', [
            'domain' => 'manager', 'owner_type' => 'transaction', 'owner_ref' => 'TX-PAY',
        ], $this->headers())->json('data');

        StatementEntry::create([
            'bank_account_id'    => BankAccount::query()->firstOrFail()->id,
            'virtual_account_id' => $created['id'],
            'direction'          => StatementEntry::DIR_CREDIT,
            'amount_minor'       => 5000,
            'currency'           => 'PLN',
            'booking_date'       => '2026-08-30',
            'counterparty_name'  => 'Jan Kowalski',
            'match_status'       => StatementEntry::MATCH_MATCHED,
            'fingerprint'        => hash('sha256', 'jeden'),
        ]);

        $this->getJson('/api/v1/virtual-accounts/'.$created['id'].'/payments', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.amount', '50.00')
            ->assertJsonPath('data.0.counterparty_name', 'Jan Kowalski');
    }

    public function test_release_closes_the_account_for_further_payments(): void
    {
        $created = $this->postJson('/api/v1/virtual-accounts', [
            'domain' => 'manager', 'owner_type' => 'transaction', 'owner_ref' => 'TX-REL',
        ], $this->headers())->json('data');

        $this->deleteJson('/api/v1/virtual-accounts/'.$created['id'], [], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', VirtualAccount::STATUS_RELEASED);

        $this->assertFalse(VirtualAccount::findOrFail($created['id'])->isOpenForPayments());
    }

    public function test_bank_accounts_endpoint_exposes_the_registry_with_balances(): void
    {
        BankAccount::query()->firstOrFail()->update([
            'is_active'               => true,
            'available_balance_minor' => 1234567,
            'booked_balance_minor'    => 1234567,
            'balance_synced_at'       => now(),
        ]);

        $this->getJson('/api/v1/bank-accounts', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.balance.available', '12345.67')
            ->assertJsonPath('data.0.purpose', BankAccount::PURPOSE_MASSCOLLECT);
    }

    public function test_domains_endpoint_shows_own_and_shared_domains(): void
    {
        $response = $this->getJson('/api/v1/domains', $this->headers())->assertOk();

        $codes = array_column($response->json('data'), 'code');

        $this->assertContains('manager', $codes);
        $this->assertNotContains('esim', $codes);
    }
}
