<?php

namespace App\Http\Controllers\Api\V1;

use App\Banking\Reconcile\RunReconciliation;
use App\Http\Requests\Api\V1\RunReconciliationRequest;
use App\Models\MasscollectDomain;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

/**
 * Zestawienie danych konsumenta z tym, co widzimy w banku.
 *
 * Konsument przysyła swoją listę oczekiwanych wpłat za okres, dostaje
 * rozbicie na cztery kubełki. Zapis w bazie zostaje, żeby dało się wrócić
 * do tego, co obie strony twierdziły w danym momencie.
 */
class ReconciliationController extends ApiController
{
    public function __construct(private readonly RunReconciliation $reconciliation)
    {
    }

    public function store(RunReconciliationRequest $request): JsonResponse
    {
        $domain = $request->filled('domain')
            ? MasscollectDomain::where('code', $request->string('domain'))->firstOrFail()
            : null;

        $result = $this->reconciliation->handle(
            $this->tenant($request),
            $request->date('period_from'),
            $request->date('period_to'),
            $request->input('items', []),
            $domain,
            (string) $request->input('currency', 'PLN'),
        );

        $record = $result['reconciliation'];

        return new JsonResponse([
            'group_no'    => $record->group_no,
            'period'      => ['from' => $record->period_from->toDateString(), 'to' => $record->period_to->toDateString()],
            'status'      => $record->status,
            'totals'      => [
                'bank'         => Money::formatMinorUnits($record->bank_total_minor),
                'counterparty' => Money::formatMinorUnits($record->counterparty_total_minor),
                'difference'   => Money::formatMinorUnits($record->difference_minor),
                'minor_units'  => [
                    'bank'         => $record->bank_total_minor,
                    'counterparty' => $record->counterparty_total_minor,
                    'difference'   => $record->difference_minor,
                ],
            ],
            'matched'             => $result['matched'],
            'amount_mismatch'     => $result['amount_mismatch'],
            'missing_in_bank'     => $result['missing_in_bank'],
            'missing_at_consumer' => $result['missing_at_consumer'],
        ], 201);
    }
}
