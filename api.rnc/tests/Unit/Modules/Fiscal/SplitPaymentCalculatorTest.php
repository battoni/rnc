<?php

use App\Modules\Fiscal\Services\SplitPaymentCalculator;

/*
 * The cash split payment holds back each month (rnc-2 R23, D9, A19's unit half): B2B sales per
 * month × 26,5 % × 40–60 % (the share settled through split) — a range. B2B sales come from the
 * client's issued invoices to a CNPJ in the last 90 days ÷ 3 ("das notas"); with no such XML, from
 * the band's representative revenue × the declared B2B share ÷ 12 ("estimativa do cadastro").
 * Pure, in integer cents, half-up.
 */
describe('the held cash from the invoices (A19)', function () {
    it('gives R$ 10.600–15.900 a month for R$ 300.000 of B2B sales in 90 days, marked das_notas', function () {
        $estimate = SplitPaymentCalculator::estimate(30_000_000, 'b3', 70);

        expect($estimate['basis'])->toBe(SplitPaymentCalculator::BASIS_NOTES)
            ->and($estimate['basis'])->toBe('das_notas')
            ->and($estimate['monthly_b2b_cents'])->toBe(10_000_000)
            // 100.000 × 0,265 × 0,4 = 10.600 and × 0,6 = 15.900
            ->and($estimate['held_per_month_cents'])->toBe([1_060_000, 1_590_000]);
    });

    it('reads zero B2B sales in the invoices as zero held, still das_notas', function () {
        $estimate = SplitPaymentCalculator::estimate(0, 'b3', 70);

        expect($estimate['basis'])->toBe('das_notas')
            ->and($estimate['held_per_month_cents'])->toBe([0, 0]);
    });
});

describe('the held cash from the cadastro (A19)', function () {
    it('uses the band revenue × the declared B2B share ÷ 12 when there is no XML, marked cadastro', function () {
        // b3 = R$ 12 mi a year (RevenueBand::revenue) × 70 % ÷ 12 = R$ 700.000 a month.
        $estimate = SplitPaymentCalculator::estimate(null, 'b3', 70);

        expect($estimate['basis'])->toBe(SplitPaymentCalculator::BASIS_CADASTRO)
            ->and($estimate['basis'])->toBe('cadastro')
            ->and($estimate['monthly_b2b_cents'])->toBe(70_000_000)
            // 700.000 × 0,265 × 0,4 = 74.200 and × 0,6 = 111.300
            ->and($estimate['held_per_month_cents'])->toBe([7_420_000, 11_130_000]);
    });

    it('reads every band at the demo representative revenue', function (string $band, int $monthlyCents) {
        expect(SplitPaymentCalculator::estimate(null, $band, 100)['monthly_b2b_cents'])->toBe($monthlyCents);
    })->with([
        ['b1', 2_000_000],        // 240 mil ÷ 12
        ['b2', 15_000_000],       // 1,8 mi ÷ 12
        ['b3', 100_000_000],      // 12 mi ÷ 12
        ['b4', 750_000_000],      // 90 mi ÷ 12
        ['b5', 3_750_000_000],    // 450 mi ÷ 12
    ]);

    it('holds nothing for a 0 % B2B client', function () {
        expect(SplitPaymentCalculator::estimate(null, 'b5', 0)['held_per_month_cents'])->toBe([0, 0]);
    });
});

describe('the rounding', function () {
    it('rounds half-up in cents', function () {
        // 100 cents in 90 days → 33 a month (33,33…); 33 × 0,265 × 0,4 = 3,498 → 3; × 0,6 = 5,247 → 5.
        // 1000 cents → 333 a month; × 0,1060 = 35,298 → 35; × 0,1590 = 52,947 → 53.
        expect(SplitPaymentCalculator::estimate(100, 'b1', 0)['held_per_month_cents'])->toBe([3, 5])
            ->and(SplitPaymentCalculator::estimate(1000, 'b1', 0)['held_per_month_cents'])->toBe([35, 53]);
    });

    it('carries the rates it used: 26,5 % reference and 40–60 % settled through split', function () {
        expect(SplitPaymentCalculator::REFERENCE_RATE_BP)->toBe(2650)
            ->and(SplitPaymentCalculator::SETTLED_SHARE_BP)->toBe([4000, 6000]);
    });
});
