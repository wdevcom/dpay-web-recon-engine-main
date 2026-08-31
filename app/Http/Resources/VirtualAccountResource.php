<?php

namespace App\Http\Resources;

use App\Models\VirtualAccount;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VirtualAccount */
class VirtualAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'iban'       => $this->iban,
            'nrb'        => $this->nrb,
            'domain'     => $this->whenLoaded('domain', fn () => $this->domain->code, $this->domain?->code),
            'owner_type' => $this->owner_type,
            'owner_ref'  => $this->owner_ref,
            'label'      => $this->label,
            'lifecycle'  => $this->lifecycle,
            'status'     => $this->status,
            'currency'   => $this->currency,
            'expected_amount'       => $this->expected_amount_minor === null ? null : Money::formatMinorUnits($this->expected_amount_minor),
            'expected_amount_minor' => $this->expected_amount_minor,
            'received_amount'       => Money::formatMinorUnits((int) $this->received_amount_minor),
            'received_amount_minor' => (int) $this->received_amount_minor,
            'payments_count'        => (int) $this->payments_count,
            'expires_at'            => $this->expires_at?->toIso8601String(),
            'first_payment_at'      => $this->first_payment_at?->toIso8601String(),
            'metadata'              => $this->metadata,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
