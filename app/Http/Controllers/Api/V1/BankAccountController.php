<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\Request;

/**
 * Rachunki rzeczywiste w BNP.
 *
 * To jest rejestr po naszej stronie - GOconnect nie udostępnia operacji
 * zwracającej listę rachunków, więc "wszystkie rachunki z BNP" znaczy
 * "wszystkie rachunki, które mamy skonfigurowane, z saldami odświeżonymi
 * z banku". Znacznik `balance.synced_at` mówi, jak świeże są te salda.
 */
class BankAccountController extends ApiController
{
    public function index(Request $request)
    {
        $accounts = BankAccount::query()
            ->when($request->query('purpose'), fn ($q, $purpose) => $q->where('purpose', $purpose))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('iban')
            ->get();

        return BankAccountResource::collection($accounts);
    }

    public function show(string $iban)
    {
        return new BankAccountResource(BankAccount::where('iban', $iban)->firstOrFail());
    }
}
