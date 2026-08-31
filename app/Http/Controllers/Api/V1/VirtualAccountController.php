<?php

namespace App\Http\Controllers\Api\V1;

use App\Banking\Masscollect\SequenceExhaustedException;
use App\Banking\Masscollect\VirtualAccountAllocator;
use App\Http\Requests\Api\V1\AllocateVirtualAccountRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\VirtualAccountResource;
use App\Models\MasscollectDomain;
use App\Models\VirtualAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wydawanie i zarządzanie mikro rachunkami.
 *
 * Alokacja jest idempotentna po `(owner_type, owner_ref)` w obrębie
 * konsumenta: powtórzone żądanie zwraca ten sam numer z kodem 200 zamiast
 * wypalać kolejny. Nowy numer to 201.
 */
class VirtualAccountController extends ApiController
{
    public function __construct(private readonly VirtualAccountAllocator $allocator)
    {
    }

    public function index(Request $request)
    {
        $accounts = VirtualAccount::query()
            ->where('tenant_id', $this->tenant($request)->id)
            ->when($request->query('owner_ref'), fn ($q, $ref) => $q->where('owner_ref', $ref))
            ->when($request->query('owner_type'), fn ($q, $type) => $q->where('owner_type', $type))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->with('domain')
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return VirtualAccountResource::collection($accounts);
    }

    public function store(AllocateVirtualAccountRequest $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $domain = MasscollectDomain::where('code', $request->string('domain'))->firstOrFail();

        if ($domain->tenant_id !== null && $domain->tenant_id !== $tenant->id) {
            return new JsonResponse(['message' => 'Domena należy do innego konsumenta.'], 403);
        }

        $alreadyExisted = VirtualAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('owner_type', $request->string('owner_type'))
            ->where('owner_ref', $request->string('owner_ref'))
            ->exists();

        try {
            $account = $this->allocator->allocate(
                $domain,
                $tenant,
                (string) $request->string('owner_type'),
                (string) $request->string('owner_ref'),
                [
                    'label'                 => $request->input('label'),
                    'expected_amount_minor' => $request->input('expected_amount_minor'),
                    'currency'              => $request->input('currency', 'PLN'),
                    'ttl_minutes'           => $request->input('ttl_minutes'),
                    'expires_at'            => $request->date('expires_at'),
                    'lifecycle'             => $request->input('lifecycle'),
                    'metadata'              => $request->input('metadata'),
                ],
            );
        } catch (SequenceExhaustedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 503);
        }

        return (new VirtualAccountResource($account->load('domain')))
            ->response()
            ->setStatusCode($alreadyExisted ? 200 : 201);
    }

    public function show(Request $request, int $id)
    {
        return new VirtualAccountResource($this->findForTenant($request, $id)->load('domain'));
    }

    /** Zamyka rachunek dla dalszych wpłat. Numer nie wraca do puli. */
    public function destroy(Request $request, int $id)
    {
        return new VirtualAccountResource(
            $this->allocator->release($this->findForTenant($request, $id))->load('domain')
        );
    }

    public function payments(Request $request, int $id)
    {
        $account = $this->findForTenant($request, $id);

        return PaymentResource::collection(
            $account->payments()->orderByDesc('booking_date')->orderByDesc('id')->paginate(50)
        );
    }

    private function findForTenant(Request $request, int $id): VirtualAccount
    {
        return VirtualAccount::query()
            ->where('tenant_id', $this->tenant($request)->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
