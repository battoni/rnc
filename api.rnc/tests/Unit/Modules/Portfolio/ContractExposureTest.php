<?php

use App\Modules\Portfolio\Helpers\ContractExposure;

/*
 * The contract alert's exposure (rnc-3 R19, AM7, AM17): the years of the 2027–2033 transition a
 * contract still runs, `min(end, 2033) − max(start, 2027) + 1`, floor 0, times its annual value.
 */
describe('the years exposed (AM17)', function () {
    it('counts the transition years the contract runs', function (int $start, int $end, int $years) {
        expect(ContractExposure::years($start, $end))->toBe($years);
    })->with([
        'ends 2026, before the transition' => [2022, 2026, 0],
        'ends 2027' => [2024, 2027, 1],
        'runs 2028–2030 (AM7)' => [2028, 2030, 3],
        'runs 2030–2033 (AM17)' => [2030, 2033, 4],
        'starts before and ends 2033' => [2022, 2033, 7],
        'ends 2035: capped at 2033 (A17)' => [2025, 2035, 7],
        'starts after 2033' => [2034, 2040, 0],
        'one year inside' => [2031, 2031, 1],
    ]);

    it('names the window', function () {
        expect(ContractExposure::FIRST_YEAR)->toBe(2027)
            ->and(ContractExposure::LAST_YEAR)->toBe(2033);
    });
});

describe('the value exposed', function () {
    it('multiplies the annual value by the years, in cents', function () {
        expect(ContractExposure::cents(120_000_000, 2025, 2035))->toBe(840_000_000)
            ->and(ContractExposure::cents(4_000_000, 2022, 2026))->toBe(0)
            ->and(ContractExposure::cents(1_234_567, 2028, 2030))->toBe(3_703_701);
    });
});
