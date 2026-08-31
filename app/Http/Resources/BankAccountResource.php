<?php

namespace App\Http\Resources;

use App\Models\BankAccount;
use App\Support\Money;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankAccount */
class BankAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'iban'     => $this->iban,
            'name'     => $this->name,
            'currency' => $this->currency,
            'purpose'  => $this->purpose,
            'active'   => $this->is_active,
            'balance'  => [
                'available' => $this->available_balance_minor === null ? null : Money::formatMinorUnits($this->available_balance_minor),
                'booked'    => $this->booked_balance_minor === null ? null : Money::formatMinorUnits($this->booked_balance_minor),
                'minor_units' => [
                    'available' => $this->available_balance_minor,
                    'booked'    => $this->booked_balance_minor,
                ],
                'synced_at' => $this->balance_synced_at?->toIso8601String(),
                'error'     => $this->sync_error,
            ],
        ];
    }
}
