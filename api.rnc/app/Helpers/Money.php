<?php

namespace App\Helpers;

/**
 * Money is an integer of the currency's minor unit (cents) everywhere past the edge that
 * receives it, so no balance ever accumulates float drift. Formatting for a person is a
 * locale's business and lives beside the locale — see `Brazil\Real::format`.
 */
class Money
{
    private const CENTS_PER_UNIT = 100;

    /** percent × 100 → basis points, so 10.00 % becomes an exact 1000 / 10000 ratio. */
    private const BASIS_POINTS_PER_PERCENT = 100;

    private const BASIS_POINTS_PER_UNIT = 10000;

    public static function toCents(float $value): int
    {
        return (int) round($value * self::CENTS_PER_UNIT);
    }

    /**
     * Integer share of an amount, rounded half up (3 × 33.33 at 10 % → 999.9 → 1000).
     * Computed in basis points so the rounding never sees a float.
     */
    public static function percentOf(int $cents, float $percent): int
    {
        $basisPoints = (int) round($percent * self::BASIS_POINTS_PER_PERCENT);
        $numerator = $cents * $basisPoints;

        if ($numerator < 0) {
            return -intdiv(-$numerator + intdiv(self::BASIS_POINTS_PER_UNIT, 2), self::BASIS_POINTS_PER_UNIT);
        }

        return intdiv($numerator + intdiv(self::BASIS_POINTS_PER_UNIT, 2), self::BASIS_POINTS_PER_UNIT);
    }
}
