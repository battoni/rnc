<?php

use App\Modules\Briefing\Helpers\FeeQuartiles;

/*
 * The pipeline's fee range (rnc-3 R24, AM7; A21's maths): a lead has no fee, so each open lead
 * borrows the portfolio's monthly fee quartiles of its band — p25 and p75, nearest-rank on the
 * cents (p25 is the ⌈0,25 n⌉-th fee) — × 12, summed. A band of fewer than 5 clients, or a lead
 * without a band, borrows every client's quartiles; an empty portfolio gives no range at all.
 * Fees arrive as `clients.monthly_fee`'s decimal string and become cents without a float.
 */
describe('FeeQuartiles (A21)', function () {
    it('takes p25 and p75 nearest-rank: the ⌈n/4⌉-th and ⌈3n/4⌉-th fee', function () {
        // n = 6: p25 is the 2nd, p75 the 5th (linear interpolation would give 2 250 and 5 750).
        $fees = new FeeQuartiles(['b3' => [600000, 100000, 300000, 200000, 500000, 400000]]);

        expect($fees->monthlyFor('b3'))->toBe([200000, 500000]);
    });

    it('rounds the rank up, never down: of 4 and of 8 fees', function () {
        // n = 4: ⌈1⌉ = 1st and ⌈3⌉ = 3rd; n = 8: ⌈2⌉ = 2nd and ⌈6⌉ = 6th (a floor would take the next one).
        expect((new FeeQuartiles(['b1' => [400, 100, 300, 200]]))->monthlyFor(null))->toBe([100, 300])
            ->and((new FeeQuartiles(['b1' => [1, 2, 3, 4, 5, 6, 7, 8]]))->monthlyFor('b1'))->toBe([2, 6]);
    });

    it('reads a band of exactly 5 clients on its own', function () {
        $fees = new FeeQuartiles([
            'b2' => [100000, 200000, 300000, 400000, 500000],
            'b4' => [900000],
        ]);

        // n = 5: ⌈1,25⌉ = 2nd, ⌈3,75⌉ = 4th of b2 alone.
        expect($fees->monthlyFor('b2'))->toBe([200000, 400000]);
    });

    it('falls back to every client for a band of fewer than 5, and for a lead without a band', function () {
        $fees = new FeeQuartiles([
            'b2' => [100000, 200000, 300000, 400000, 500000],
            'b4' => [900000, 1000000],
        ]);

        // Every client: 1 000 · 2 000 · 3 000 · 4 000 · 5 000 · 9 000 · 10 000 (n = 7): 2nd and 6th.
        expect($fees->monthlyFor('b4'))->toBe([200000, 900000])
            ->and($fees->monthlyFor(null))->toBe([200000, 900000])
            ->and($fees->monthlyFor('b5'))->toBe([200000, 900000]);
    });

    it('sums each lead’s quartiles × 12 into the annual range', function () {
        $fees = new FeeQuartiles([
            'b2' => [100000, 200000, 300000, 400000, 500000],
            'b4' => [900000, 1000000],
        ]);

        // b2 → [2 000, 4 000]; b4 and no band → every client's [2 000, 9 000]. Σ = [6 000, 22 000] × 12.
        expect($fees->annualRange(['b2', 'b4', null]))->toBe([7200000, 26400000])
            ->and($fees->annualRange([]))->toBe([0, 0]);
    });

    it('gives no range on an empty portfolio', function () {
        $fees = new FeeQuartiles([]);

        expect($fees->monthlyFor('b2'))->toBeNull()
            ->and($fees->annualRange(['b2']))->toBeNull()
            ->and($fees->annualRange([]))->toBeNull();
    });

    it('reads the decimal string into cents without a float', function (string|int|float $stored, int $cents) {
        expect(FeeQuartiles::cents($stored))->toBe($cents);
    })->with([
        'two decimals' => ['3000.00', 300000],
        'one decimal' => ['1234.5', 123450],
        'whole' => ['900', 90000],
        'odd cents' => ['0.29', 29],
        'an integer from SQLite' => [1800, 180000],
        'a real from SQLite' => [4100.07, 410007],
    ]);
});
