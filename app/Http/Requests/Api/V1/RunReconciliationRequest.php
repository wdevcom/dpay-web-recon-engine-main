<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RunReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_from'          => ['required', 'date'],
            'period_to'            => ['required', 'date', 'after_or_equal:period_from'],
            'domain'               => ['nullable', 'string', 'exists:masscollect_domains,code'],
            'currency'             => ['nullable', 'string', 'size:3'],
            'items'                => ['present', 'array'],
            'items.*.owner_ref'    => ['required', 'string', 'max:128'],
            'items.*.amount_minor' => ['required', 'integer'],
        ];
    }
}
