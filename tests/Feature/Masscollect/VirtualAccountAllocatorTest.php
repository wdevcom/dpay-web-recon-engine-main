<?php

namespace Tests\Feature\Masscollect;

use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Models\MasscollectDomain;
use App\Models\VirtualAccount;
use Database\Seeders\BankingSeeder;
use Dpayglobal\DpayLibaryBnpparibasGoconnectPlSrc\Common\VO\BankAccountNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualAccountAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private VirtualAccountAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankingSeeder::class);
        $this->allocator = app(VirtualAccountAllocator::class);
    }

    private function domain(string $code): MasscollectDomain
    {
        return MasscollectDomain::where('code', $code)->firstOrFail();
    }

    public function test_allocates_sequential_numbers_within_a_domain(): void
    {
        $domain = $this->domain('manager');

        $first = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-1');
        $second = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-2');

        $this->assertSame(1, $first->sequence);
        $this->assertSame(2, $second->sequence);
        $this->assertSame(3, $domain->fresh()->next_sequence);
        $this->assertSame(2, $domain->fresh()->allocated_count);

        BankAccountNumber::fromString($first->iban);
        BankAccountNumber::fromString($second->iban);
    }

    public function test_repeated_request_for_the_same_owner_returns_the_same_number(): void
    {
        $domain = $this->domain('manager');

        $first = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-42');
        $again = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-42');

        // Konsument, który ponowi żądanie po timeoucie, nie może dostać
        // drugiego numeru - wpłata trafiłaby na rachunek, o którym już
        // zapomniał.
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, VirtualAccount::count());
        $this->assertSame(2, $domain->fresh()->next_sequence);
    }

    public function test_the_same_owner_ref_in_two_domains_gets_two_numbers(): void
    {
        $manager = $this->domain('manager');
        $eid = $this->domain('eid');

        $a = $this->allocator->allocate($manager, $manager->tenant, 'transaction', 'SHARED-1');
        $b = $this->allocator->allocate($eid, $eid->tenant, 'verification', 'SHARED-1');

        $this->assertNotSame($a->iban, $b->iban);
        $this->assertSame('1', substr($a->nrb, 2 + 8 + 4, 1));
        $this->assertSame('2', substr($b->nrb, 2 + 8 + 4, 1));
    }

    public function test_lifecycle_drives_expiry(): void
    {
        $manager = $this->domain('manager');
        $esim = $this->domain('esim');

        $temporary = $this->allocator->allocate($manager, $manager->tenant, 'transaction', 'TX-TTL');
        $permanent = $this->allocator->allocate($esim, $esim->tenant, 'location', 'LOC-1');

        $this->assertSame(MasscollectDomain::LIFECYCLE_ONE_TIME, $temporary->lifecycle);
        $this->assertNotNull($temporary->expires_at);

        // Rachunek, który jest tożsamością lokalizacji, nie wygasa.
        $this->assertSame(MasscollectDomain::LIFECYCLE_PERSISTENT, $permanent->lifecycle);
        $this->assertNull($permanent->expires_at);
    }

    public function test_released_number_is_not_returned_to_the_pool(): void
    {
        $domain = $this->domain('manager');

        $account = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-REL');
        $this->allocator->release($account);

        $next = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-NEXT');

        $this->assertSame(VirtualAccount::STATUS_RELEASED, $account->fresh()->status);
        $this->assertSame(2, $next->sequence);
        $this->assertNotSame($account->nrb, $next->nrb);
    }

    public function test_resolve_finds_the_account_in_any_notation_and_ignores_foreign_numbers(): void
    {
        $domain = $this->domain('manager');
        $account = $this->allocator->allocate($domain, $domain->tenant, 'transaction', 'TX-RES');

        $this->assertSame($account->id, $this->allocator->resolve($account->iban)?->id);
        $this->assertSame($account->id, $this->allocator->resolve($account->nrb)?->id);
        $this->assertSame($account->id, $this->allocator->resolve(chunk_split($account->nrb, 4, ' '))?->id);
        $this->assertNull($this->allocator->resolve('56114020040000000000000000'));
    }

    public function test_refuses_to_allocate_from_an_exhausted_domain(): void
    {
        $domain = $this->domain('manager');
        $domain->update(['next_sequence' => app(\App\Banking\Masscollect\AccountNumberMask::class)->capacityPerDomain()]);

        $this->expectException(\App\Banking\Masscollect\SequenceExhaustedException::class);

        $this->allocator->allocate($domain->fresh(), $domain->tenant, 'transaction', 'TX-BOOM');
    }
}
