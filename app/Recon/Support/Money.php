<?php

namespace App\Recon\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Thin wrapper around brick/math for our domain. All monetary values
 * everywhere in the recon engine must round-trip through this class so
 * we never hit float drift on prowizja calculations.
 */
final class Money
{
    public static function of(string|int|float|BigDecimal|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }
        if ($value instanceof BigDecimal) {
            return $value->toScale(2, RoundingMode::HALF_UP);
        }

        // tolerate Polish bank "1 230 387,46" and "1230387.46" alike
        $clean = trim((string) $value);
        $clean = str_replace([' ', "\u{a0}"], '', $clean);
        $clean = str_replace(',', '.', $clean);

        return BigDecimal::of($clean)->toScale(2, RoundingMode::HALF_UP);
    }

    public static function isZero(BigDecimal $value): bool
    {
        return $value->isZero();
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
