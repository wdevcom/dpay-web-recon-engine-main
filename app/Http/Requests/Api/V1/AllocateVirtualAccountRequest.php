<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AllocateVirtualAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain'                => ['required', 'string', 'exists:masscollect_domains,code'],
            'owner_type'            => ['required', 'string', 'max:64'],
            'owner_ref'             => ['required', 'string', 'max:128'],
            'label'                 => ['nullable', 'string', 'max:255'],
            'expected_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency'              => ['nullable', 'string', 'size:3'],
            'ttl_minutes'           => ['nullable', 'integer', 'min:1'],
            'expires_at'            => ['nullable', 'date'],
            'lifecycle'             => ['nullable', 'in:one_time,persistent'],
            'metadata'              => ['nullable', 'array'],
        ];
    }
}
