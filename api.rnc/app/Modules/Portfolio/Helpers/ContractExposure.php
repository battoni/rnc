<?php

namespace App\Modules\Portfolio\Helpers;

/**
 * How much of a contract runs through the Reforma's transition (rnc-3 R19, D10, AM7, AM17). Pure.
 *
 * Years exposed = `min(end, LAST_YEAR) − max(start, FIRST_YEAR) + 1`, floor 0: a contract of
 * 2030–2033 runs 4 transition years, one of 2025–2035 runs 7 (capped at 2033, the transition's end).
 * The value exposed is the annual value times those years, in integer cents.
 */
final class ContractExposure
{
    /** CBS starts charging in 2027 (facts.md §2). */
    public const FIRST_YEAR = 2027;

    /** The transition ends in 2033; later years are not counted (D10). */
    public const LAST_YEAR = 2033;

    public static function years(int $startYear, int $endYear): int
    {
        return max(0, min($endYear, self::LAST_YEAR) - max($startYear, self::FIRST_YEAR) + 1);
    }

    public static function cents(int $annualValueCents, int $startYear, int $endYear): int
    {
        return $annualValueCents * self::years($startYear, $endYear);
    }
}
