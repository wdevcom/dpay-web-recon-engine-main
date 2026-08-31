<?php

namespace App\Banking\Masscollect;

/**
 * Wygenerowany numer rachunku wirtualnego w obu zapisach, których wymagają
 * różne pola: NRB (26 cyfr) w komunikatach krajowych, IBAN w kryteriach
 * zapytań GOconnect.
 */
final class VirtualAccountNumber
{
    public function __construct(
        public readonly string $nrb,
        public readonly string $domainDigits,
        public readonly int $sequence,
    ) {
    }

    public static function generate(AccountNumberMask $mask, string $domainDigits, int $sequence): self
    {
        return new self($mask->build($domainDigits, $sequence), $domainDigits, $sequence);
    }

    public function iban(): string
    {
        return 'PL'.$this->nrb;
    }

    /** Zapis dla człowieka: "12 3456 7890 1234 5678 9012 3456". */
    public function formatted(): string
    {
        return trim(chunk_split(substr($this->nrb, 0, 2), 2, ' ').trim(chunk_split(substr($this->nrb, 2), 4, ' ')));
    }

    public function __toString(): string
    {
        return $this->iban();
    }
}
