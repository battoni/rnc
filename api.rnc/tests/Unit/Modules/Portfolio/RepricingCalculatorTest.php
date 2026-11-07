<?php

use App\Modules\Diagnostic\DTOs\DiagnosticProfileDTO;
use App\Modules\Diagnostic\Services\RulesEngine;
use App\Modules\Portfolio\Services\RepricingCalculator;

/*
 * The repricing rule (rnc-1 R26, demo `repricing()` W32/W43): each opportunity of the client's
 * diagnosis weighs 1,5 points and each risk 2,5; the sum is the uplift in percent, kept in 4–25 %;
 * the new fee rounds to R$ 50 and stays inside 4–25 % of today's (floor/ceil to R$ 50 at the edges);
 * `pct` is the one the ROUNDED fee really gives.
 */
function repricingCalculator(): RepricingCalculator
{
    return new RepricingCalculator(new RulesEngine);
}

describe('the suggested fee', function () {
    it('rounds the target to R$ 50', function () {
        expect(RepricingCalculator::suggestedFee(1000.0, 7.5))->toBe(1100.0);
    });

    it('floors to R$ 50 when the rounding would pass 25 %', function () {
        expect(RepricingCalculator::suggestedFee(1030.0, 25.0))->toBe(1250.0);
    });

    it('ceils to R$ 50 when the rounding would fall under 4 %', function () {
        expect(RepricingCalculator::suggestedFee(1210.0, 4.0))->toBe(1300.0);
    });

    it('keeps a fee that lands exactly on an edge', function () {
        expect(RepricingCalculator::suggestedFee(1250.0, 4.0))->toBe(1300.0)
            ->and(RepricingCalculator::suggestedFee(1000.0, 25.0))->toBe(1250.0);
    });
});

describe('a client quote', function () {
    it('weighs 1,5 per opportunity and 2,5 per risk, and reads pct from the rounded fee', function () {
        $profile = new DiagnosticProfileDTO(sector: 'servicos', regime: 'simples', band: 'b2', b2b: 80);
        $diagnosis = (new RulesEngine)->diagnose($profile);

        $quote = repricingCalculator()->quote($profile, 2000.0);

        $raw = 1.5 * $diagnosis['opportunities'] + 2.5 * $diagnosis['risks'];
        $target = min(25, max(4, $raw));
        $expectedFee = RepricingCalculator::suggestedFee(2000.0, $target);

        expect($quote['items'])->toHaveCount(count($diagnosis['items']))
            ->and($quote['current'])->toBe(2000.0)
            ->and($quote['suggested'])->toBe($expectedFee)
            ->and($quote['pct'])->toBe((int) round(($expectedFee / 2000 - 1) * 100))
            ->and($quote['uplift'])->toBe(($expectedFee - 2000) * 12);
    });

    it('keeps every pct inside 4–25 % after rounding, on every profile', function () {
        $calculator = repricingCalculator();
        $pcts = [];

        foreach (['comercio', 'industria', 'servicos', 'saude', 'educacao', 'agro'] as $sector) {
            foreach (['simples', 'presumido', 'real'] as $regime) {
                foreach (['b1', 'b2', 'b3', 'b4', 'b5'] as $band) {
                    foreach ([250.0, 900.0, 1234.56, 1250.0, 9000.0] as $fee) {
                        $pcts[] = $calculator->quote(new DiagnosticProfileDTO(sector: $sector, regime: $regime, band: $band, b2b: 60), $fee)['pct'];
                    }
                }
            }
        }

        expect(min($pcts))->toBeGreaterThanOrEqual(4)
            ->and(max($pcts))->toBeLessThanOrEqual(25)
            ->and(count(array_unique($pcts)))->toBeGreaterThan(3);
    });
});
