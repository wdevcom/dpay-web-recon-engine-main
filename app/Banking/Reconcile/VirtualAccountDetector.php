<?php

namespace App\Banking\Reconcile;

use App\Banking\Masscollect\AccountNumberMask;

/**
 * Wyciąga numer rachunku wirtualnego z pozycji wyciągu.
 *
 * Bank podaje go na dwa sposoby, zależnie od produktu i formatu komunikatu:
 *  - jako rachunek uznany (`CdtrAcct`) - tak jest w wyciągu MBR,
 *  - w tytule operacji, w drugim wystąpieniu `RmtInf/Ustrd` - tak bywa
 *    w zbiorczej historii rachunku masowego.
 *
 * Kandydat jest przyjmowany dopiero po sprawdzeniu maski I sumy kontrolnej,
 * bo w tytule przelewu potrafi wylądować dowolny 26-cyfrowy ciąg.
 */
class VirtualAccountDetector
{
    public function __construct(private readonly AccountNumberMask $mask)
    {
    }

    /**
     * @param  string|null  $creditedAccount  rachunek uznany z pozycji
     * @param  string|null  $remittanceText   tytuł operacji
     */
    public function detect(?string $creditedAccount, ?string $remittanceText): ?string
    {
        if ($creditedAccount !== null && $this->isOurs($creditedAccount)) {
            return AccountNumberMask::normalize($creditedAccount);
        }

        foreach ($this->candidatesFromText($remittanceText) as $candidate) {
            if ($this->isOurs($candidate)) {
                return AccountNumberMask::normalize($candidate);
            }
        }

        return null;
    }

    /** Numer należy do naszej maski i ma poprawną sumę kontrolną. */
    public function isOurs(string $accountNumber): bool
    {
        $nrb = AccountNumberMask::normalize($accountNumber);

        if (! $this->mask->matches($nrb)) {
            return false;
        }

        return $this->mask->checkDigits(substr($nrb, 2)) === substr($nrb, 0, 2);
    }

    /**
     * @return list<string>
     */
    private function candidatesFromText(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        // Numer w tytule bywa łamany spacjami po 4 cyfry - dopuszczamy
        // pojedyncze spacje między cyframi, ale nie sklejamy całego tytułu,
        // żeby nie zlepić dwóch niezależnych liczb w fałszywy numer.
        if (preg_match_all('/(?:PL)?(?:\d[ ]?){25}\d/', $text, $matches) !== 1 && empty($matches[0])) {
            return [];
        }

        return array_values(array_map(
            static fn (string $match): string => (string) preg_replace('/\s/', '', $match),
            $matches[0] ?? []
        ));
    }
}
