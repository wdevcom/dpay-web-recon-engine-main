<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Kwoty w tym serwisie żyją w dwóch postaciach i obie muszą być dokładne:
 *  - grosze jako `int` - tak mówi bank (biblioteka GOconnect trzyma Amount
 *    w jednostkach minorowych), więc tak trzymamy pozycje z wyciągów,
 *  - BigDecimal ze skalą 2 - tak liczy księga.
 *
 * Ta klasa jest jedynym miejscem konwersji między nimi. Nigdzie w kodzie
 * nie ma prawa pojawić się float; `0.1 + 0.2 !== 0.3` w rozliczeniach to
 * nie ciekawostka, tylko rozjazd.
 */
final class Money
{
    public static function of(string|int|float|BigDecimal|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero()->toScale(2);
        }
        if ($value instanceof BigDecimal) {
            return $value->toScale(2, RoundingMode::HALF_UP);
        }

        // tolerancja dla zapisów z systemów FK: "1 230 387,46" i "1230387.46"
        $clean = trim((string) $value);
        $clean = str_replace([' ', "\u{a0}"], '', $clean);
        $clean = str_replace(',', '.', $clean);

        return BigDecimal::of($clean)->toScale(2, RoundingMode::HALF_UP);
    }

    /** Grosze z banku -> kwota księgowa. */
    public static function fromMinorUnits(int $minorUnits): BigDecimal
    {
        return BigDecimal::ofUnscaledValue($minorUnits, 2);
    }

    /** Kwota księgowa -> grosze. */
    public static function toMinorUnits(BigDecimal|string|int $value): int
    {
        return self::of($value)->getUnscaledValue()->toInt();
    }

    /** Sformatowana kwota z groszy, do logów i UI: "1234.56". */
    public static function formatMinorUnits(int $minorUnits): string
    {
        return (string) self::fromMinorUnits($minorUnits);
    }

    public static function equalWithin(BigDecimal $a, BigDecimal $b, string $tolerance = '0.00'): bool
    {
        return $a->minus($b)->abs()->isLessThanOrEqualTo(self::of($tolerance));
    }

    public static function asString(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HALF_UP);
    }
}
