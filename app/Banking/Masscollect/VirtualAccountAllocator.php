<?php

namespace App\Banking\Masscollect;

use App\Models\MasscollectDomain;
use App\Models\Tenant;
use App\Models\VirtualAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wydaje numery mikro rachunków z puli domeny.
 *
 * Dwie własności, na których stoi cała reszta:
 *
 *  1. ALOKACJA JEST IDEMPOTENTNA. Klucz `(tenant, owner_type, owner_ref)`
 *     jest unikalny, więc konsument, który powtórzy żądanie po timeoucie,
 *     dostanie ten sam numer. Bez tego jedna transakcja miałaby dwa
 *     rachunki i wpłata trafiłaby na ten, o którym konsument już zapomniał.
 *
 *  2. NUMERY NIE WRACAJĄ DO PULI. Licznik tylko rośnie. Recykling numeru
 *     oznacza, że spóźniona wpłata na wygasły rachunek zostaje zaksięgowana
 *     nowemu właścicielowi - to najdroższy błąd w takim systemie, a przy
 *     miliardach wolnych numerów oszczędność jest żadna.
 */
class VirtualAccountAllocator
{
    public function __construct(private readonly AccountNumberMask $mask)
    {
    }

    /**
     * @param  array{label?: string|null, expected_amount_minor?: int|null, currency?: string,
     *               expires_at?: \DateTimeInterface|null, ttl_minutes?: int|null,
     *               lifecycle?: string|null, metadata?: array|null}  $options
     */
    public function allocate(
        MasscollectDomain $domain,
        Tenant $tenant,
        string $ownerType,
        string $ownerRef,
        array $options = [],
    ): VirtualAccount {
        $existing = VirtualAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('owner_type', $ownerType)
            ->where('owner_ref', $ownerRef)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $domain->is_active) {
            throw new \DomainException(sprintf('Domena "%s" jest wyłączona.', $domain->code));
        }

        return DB::transaction(function () use ($domain, $tenant, $ownerType, $ownerRef, $options) {
            /** @var MasscollectDomain $locked */
            $locked = MasscollectDomain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();

            $sequence = $locked->next_sequence;
            $capacity = $this->mask->capacityPerDomain();

            if ($sequence >= $capacity) {
                throw new SequenceExhaustedException(
                    sprintf('Domena "%s" wyczerpała pulę %d numerów.', $locked->code, $capacity)
                );
            }

            $number = VirtualAccountNumber::generate($this->mask, $locked->digits, $sequence);
            $lifecycle = $options['lifecycle'] ?? $locked->lifecycle;

            $account = VirtualAccount::create([
                'iban'                  => $number->iban(),
                'nrb'                   => $number->nrb,
                'masscollect_domain_id' => $locked->id,
                'sequence'              => $sequence,
                'tenant_id'             => $tenant->id,
                'owner_type'            => $ownerType,
                'owner_ref'             => $ownerRef,
                'label'                 => $options['label'] ?? null,
                'lifecycle'             => $lifecycle,
                'status'                => VirtualAccount::STATUS_ALLOCATED,
                'expected_amount_minor' => $options['expected_amount_minor'] ?? null,
                'currency'              => $options['currency'] ?? 'PLN',
                'received_amount_minor' => 0,
                'payments_count'        => 0,
                'expires_at'            => $this->resolveExpiry($locked, $lifecycle, $options),
                'metadata'              => $options['metadata'] ?? null,
            ]);

            $locked->update([
                'next_sequence'   => $sequence + 1,
                'allocated_count' => $locked->allocated_count + 1,
            ]);

            if ($locked->fresh()->isRunningLow()) {
                Log::warning('Pula numerów masscollect na wyczerpaniu', [
                    'domain'    => $locked->code,
                    'remaining' => $locked->fresh()->remainingCapacity(),
                ]);
            }

            return $account;
        });
    }

    /**
     * Zamyka rachunek dla dalszych wpłat. Numer NIE wraca do puli -
     * wpłata, która przyjdzie później, zostanie rozpoznana jako wpłata na
     * zamknięty rachunek i trafi do wyjaśnienia, a nie do obcego zlecenia.
     */
    public function release(VirtualAccount $account): VirtualAccount
    {
        $account->update([
            'status'      => VirtualAccount::STATUS_RELEASED,
            'released_at' => CarbonImmutable::now(),
        ]);

        return $account->fresh();
    }

    /** Rachunek wirtualny po numerze - w dowolnym zapisie (IBAN, NRB, ze spacjami). */
    public function resolve(string $accountNumber): ?VirtualAccount
    {
        $nrb = AccountNumberMask::normalize($accountNumber);

        if (! $this->mask->matches($nrb)) {
            return null;
        }

        return VirtualAccount::query()->where('nrb', $nrb)->first();
    }

    private function resolveExpiry(MasscollectDomain $domain, string $lifecycle, array $options): ?CarbonImmutable
    {
        if (array_key_exists('expires_at', $options) && $options['expires_at'] !== null) {
            return CarbonImmutable::instance(
                $options['expires_at'] instanceof \DateTimeInterface
                    ? $options['expires_at']
                    : new \DateTimeImmutable((string) $options['expires_at'])
            );
        }

        // Rachunek, który jest tożsamością (lokalizacja, partner), nie wygasa.
        if ($lifecycle === MasscollectDomain::LIFECYCLE_PERSISTENT) {
            return null;
        }

        $ttl = $options['ttl_minutes'] ?? $domain->default_ttl_minutes;

        return $ttl === null ? null : CarbonImmutable::now()->addMinutes((int) $ttl);
    }
}
