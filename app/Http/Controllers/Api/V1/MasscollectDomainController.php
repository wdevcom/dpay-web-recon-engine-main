<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\MasscollectDomain;
use Illuminate\Http\Request;

/** Domeny dostępne dla konsumenta - jego własne i współdzielone. */
class MasscollectDomainController extends ApiController
{
    public function index(Request $request)
    {
        $tenant = $this->tenant($request);

        $domains = MasscollectDomain::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->orderBy('digits')
            ->get();

        return response()->json([
            'data' => $domains->map(fn (MasscollectDomain $d) => [
                'code'                => $d->code,
                'name'                => $d->name,
                'digits'              => $d->digits,
                'lifecycle'           => $d->lifecycle,
                'default_ttl_minutes' => $d->default_ttl_minutes,
                'allocated_count'     => $d->allocated_count,
                'remaining_capacity'  => $d->remainingCapacity(),
            ])->values(),
        ]);
    }
}
