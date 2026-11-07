<?php

use App\Modules\Fiscal\Services\SupplierCreditCalculator;

/*
 * The credit a client loses per year from 2027 by buying from Simples/MEI suppliers (rnc-2 R21, D8,
 * A18): purchases/month × 12 × Simples share × (26,5 % − DAS share), the DAS share a range 3–5 %
 * (facts.md §2 "Could not read", §5) — so the answer is always a range, never one figure. Pure, in
 * integer cents, half-up.
 */
describe('the lost credit (A18)', function () {
    it('gives R$ 77.400–84.600 a year for R$ 100.000 of purchases a month, 30 % from Simples', function () {
        // 90 days = 3 months: R$ 300.000 bought, R$ 90.000 of it from Simples suppliers.
        $estimate = SupplierCreditCalculator::estimate(30_000_000, 9_000_000);

        expect($estimate['monthly_purchases_cents'])->toBe(10_000_000)
            ->and($estimate['simples_share'])->toBe(30.0)
            // 100.000 × 12 × 0,30 × 0,215 = 77.400 and × 0,235 = 84.600
            ->and($estimate['lost_per_year_cents'])->toBe([7_740_000, 8_460_000]);
    });

    it('gives 0–0 when nothing is bought from Simples', function () {
        $estimate = SupplierCreditCalculator::estimate(30_000_000, 0);

        expect($estimate['simples_share'])->toBe(0.0)
            ->and($estimate['lost_per_year_cents'])->toBe([0, 0]);
    });

    it('gives 0–0 and a 0 % share when nothing was bought at all', function () {
        $estimate = SupplierCreditCalculator::estimate(0, 0);

        expect($estimate['monthly_purchases_cents'])->toBe(0)
            ->and($estimate['simples_share'])->toBe(0.0)
            ->and($estimate['lost_per_year_cents'])->toBe([0, 0]);
    });

    it('rounds half-up in cents and keeps the share to one decimal', function () {
        // R$ 1,00 bought, all from Simples: 100 cents × 4 × 0,215 = 86 and × 0,235 = 94.
        $estimate = SupplierCreditCalculator::estimate(100, 100);
        // 1 of 3 cents from Simples: 33,3 %.
        $third = SupplierCreditCalculator::estimate(3, 1);

        expect($estimate['lost_per_year_cents'])->toBe([86, 94])
            ->and($estimate['monthly_purchases_cents'])->toBe(33)
            ->and($third['simples_share'])->toBe(33.3);
    });

    it('carries the rates it used, with the reference rate at 26,5 % and the DAS share 3–5 %', function () {
        expect(SupplierCreditCalculator::REFERENCE_RATE_BP)->toBe(2650)
            ->and(SupplierCreditCalculator::DAS_SHARE_BP)->toBe([300, 500]);
    });
});

describe('the supplier regime (D8)', function () {
    it('reads CRT 1, 2 and 4 as Simples/MEI and 3 as regime normal', function () {
        expect(SupplierCreditCalculator::isSimples(1))->toBeTrue()
            ->and(SupplierCreditCalculator::isSimples(2))->toBeTrue()
            ->and(SupplierCreditCalculator::isSimples(4))->toBeTrue()
            ->and(SupplierCreditCalculator::isSimples(3))->toBeFalse()
            ->and(SupplierCreditCalculator::isSimples(null))->toBeFalse();
    });
});
