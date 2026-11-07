<?php

namespace App\Modules\Fiscal\Services;

/**
 * The credit a client loses per year from 2027 by buying from Simples/MEI suppliers (rnc-2 R21, D8,
 * A18). Pure, integer cents, half-up.
 *
 *   lost per year = purchases/month × 12 × Simples share × (26,5 % − DAS share), DAS share 3–5 %
 *
 * Over the 90-day window that is the Simples purchases × 12 ÷ 3 × (rate − DAS share): the high end
 * of the range takes the 3 % DAS share, the low end the 5 %. Both rates are ESTIMATES — the reference
 * rate is the "about 26,5 %" of facts.md §2 ("Could not read": only about 26,5 % was found) and the
 * DAS share a Simples supplier passes as credit is unverified (facts.md §2 Simples, §5) — so the
 * screen shows a range, never one figure (W7). A supplier is Simples/MEI by its CRT 1, 2 or 4;
 * CRT 3 is regime normal (facts.md §5; the XML cannot tell Presumido from Real — D8). An unread CRT
 * counts as not Simples: the estimate never grows on a guess.
 */
class SupplierCreditCalculator
{
    /** facts.md §2 "Reference rate estimate: about 26.5% combined" — in basis points. */
    public const REFERENCE_RATE_BP = 2650;

    /** facts.md §5: "the Simples supplier's DAS share passed as credit (~3–5 %) is unverified". */
    public const DAS_SHARE_BP = [300, 500];

    /** facts.md §5 CRT: 1 Simples Nacional, 2 Simples with excess sub-limit, 4 MEI. */
    public const SIMPLES_CRTS = [1, 2, 4];

    /** The purchases window (plan Defaults: 90 days) in months. */
    public const WINDOW_MONTHS = 3;

    private const MONTHS_IN_YEAR = 12;

    private const BASIS_POINTS = 10_000;

    /**
     * @return array{monthly_purchases_cents: int, simples_share: float, lost_per_year_cents: array{0: int, 1: int}}
     */
    public static function estimate(int $purchasesCents, int $simplesCents, int $months = self::WINDOW_MONTHS): array
    {
        $yearly = $simplesCents * self::MONTHS_IN_YEAR;
        $divisor = $months * self::BASIS_POINTS;

        return [
            'monthly_purchases_cents' => self::divideHalfUp($purchasesCents, $months),
            'simples_share' => $purchasesCents > 0 ? round($simplesCents * 100 / $purchasesCents, 1) : 0.0,
            'lost_per_year_cents' => [
                self::divideHalfUp($yearly * (self::REFERENCE_RATE_BP - self::DAS_SHARE_BP[1]), $divisor),
                self::divideHalfUp($yearly * (self::REFERENCE_RATE_BP - self::DAS_SHARE_BP[0]), $divisor),
            ],
        ];
    }

    public static function isSimples(?int $crt): bool
    {
        return $crt !== null && in_array($crt, self::SIMPLES_CRTS, true);
    }

    /** Non-negative integers only (cents and counts). */
    private static function divideHalfUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend * 2 + $divisor, $divisor * 2);
    }
}
