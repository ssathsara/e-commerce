<?php

namespace App\Support;

/**
 * Money is stored and calculated as integers in minor units (cents) to avoid
 * floating point rounding errors. Conversion to/from major units happens only
 * at the API boundary.
 */
final class Money
{
    public static function toCents(float|int|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function toMajor(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    /** Percentage of an amount, rounded half-up to the nearest cent. */
    public static function percentOf(int $cents, float $percent): int
    {
        return (int) round($cents * $percent / 100);
    }
}
