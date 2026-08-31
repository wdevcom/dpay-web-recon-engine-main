<?php

namespace App\Http\Resources;

use App\Models\StatementEntry;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StatementEntry */
class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'direction'          => $this->direction,
            'amount'             => Money::formatMinorUnits($this->amount_minor),
            'amount_minor'       => $this->amount_minor,
            'currency'           => $this->currency,
            'booking_date'       => $this->booking_date?->toDateString(),
            'value_date'         => $this->value_date?->toDateString(),
            'counterparty_name'  => $this->counterparty_name,
            'counterparty_account' => $this->counterparty_account,
            'title'              => $this->remittance_text,
            'virtual_account'    => $this->detected_virtual_account,
            'end_to_end_id'      => $this->end_to_end_id,
            'match_status'       => $this->match_status,
            'match_reason'       => $this->match_reason,
        ];
    }
}
