<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Diagnostic\Enums\RevenueBand;

/**
 * The cash split payment holds back each month (rnc-2 R23, D9, A19). Pure, integer cents, half-up.
 *
 *   held per month = B2B sales per month × 26,5 % × 40–60 % (the share settled through split)
 *
 * B2B sales per month come from the client's issued invoices to a CNPJ over the last 90 days ÷ 3
 * (`das_notas`); a client with no such XML gets its band's representative revenue × the declared
 * B2B share ÷ 12 (`cadastro`) — `RevenueBand::revenue()`, the demo's 240 mil · 1,8 mi · 12 mi ·
 * 90 mi · 450 mi, the base `RulesEngine` scales every range from. Both rates are ESTIMATES: the
 * reference rate is facts.md §2's "about 26,5 %", and the settled share is the demo's single 50 %
 * widened to a range (W7; facts.md §2 "Credits and split payment": the simplified method is
 * optional, so not every sale settles through split). Simples clients are included (D9). No
 * endpoint of its own: the Radar (F9) reads it.
 */
class SplitPaymentCalculator
{
    public const BASIS_NOTES = 'das_notas';

    public const BASIS_CADASTRO = 'cadastro';

    /** facts.md §2 "Reference rate estimate: about 26.5% combined" — in basis points. */
    public const REFERENCE_RATE_BP = 2650;

    /** The share of B2B sales settled through split, low and high (plan Defaults 40–60 %, W7). */
    public const SETTLED_SHARE_BP = [4000, 6000];

    /** The sales window (plan Defaults: 90 days) in months. */
    public const WINDOW_MONTHS = 3;

    private const MONTHS_IN_YEAR = 12;

    private const BASIS_POINTS_SQUARED = 100_000_000;

    private const CENTS = 100;

    private const PERCENT = 100;

    /**
     * `$b2bSalesCents` is the window's sales to a CNPJ, or null when the client has no issued
     * invoice in the window — then the cadastro's band and declared share are used.
     *
     * @return array{basis: string, monthly_b2b_cents: int, held_per_month_cents: array{0: int, 1: int}}
     */
    public static function estimate(?int $b2bSalesCents, string $band, int $declaredB2bShare): array
    {
        $fromNotes = $b2bSalesCents !== null;
        $monthly = $fromNotes
            ? self::divideHalfUp($b2bSalesCents, self::WINDOW_MONTHS)
            : self::divideHalfUp((int) round(RevenueBand::from($band)->revenue() * self::CENTS) * $declaredB2bShare, self::PERCENT * self::MONTHS_IN_YEAR);

        return [
            'basis' => $fromNotes ? self::BASIS_NOTES : self::BASIS_CADASTRO,
            'monthly_b2b_cents' => $monthly,
            'held_per_month_cents' => [
                self::divideHalfUp($monthly * self::REFERENCE_RATE_BP * self::SETTLED_SHARE_BP[0], self::BASIS_POINTS_SQUARED),
                self::divideHalfUp($monthly * self::REFERENCE_RATE_BP * self::SETTLED_SHARE_BP[1], self::BASIS_POINTS_SQUARED),
            ],
        ];
    }

    /** Non-negative integers only (cents). */
    private static function divideHalfUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend * 2 + $divisor, $divisor * 2);
    }
}
