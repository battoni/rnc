<?php

use App\Modules\Channel\Helpers\ChannelShare;

/*
 * A7 (rnc-3 R8, plan P5): Franco's share of an RNC firm's monthly fee is
 * `intdiv(fee_cents × share_bp + 5000, 10000)` — half-up, in integer cents, per firm. The odd-cents
 * case is the one truncation gets wrong: R$ 3.500,10 at 15 % is R$ 525,015, so R$ 525,02 (a cast
 * or an `intdiv` without the 5000 gives 525,01).
 */
describe('ChannelShare (A7)', function () {
    it('gives R$ 525 of R$ 3.500 at 15 %', function () {
        expect(ChannelShare::of(350000, 1500))->toBe(52500);
    });

    it('rounds half-up on odd cents: R$ 3.500,10 at 15 % is R$ 525,02, not 525,01', function () {
        expect(ChannelShare::of(350010, 1500))->toBe(52502);
    });

    it('rounds a share under half a cent down and exactly half a cent up', function () {
        expect(ChannelShare::of(3, 1500))->toBe(0)   // 0,45 cent
            ->and(ChannelShare::of(1, 5000))->toBe(1) // 0,5 cent
            ->and(ChannelShare::of(10, 1500))->toBe(2); // 1,5 cents
    });

    it('answers zero at a 0 % share and the whole fee at 100 %', function () {
        expect(ChannelShare::of(350000, 0))->toBe(0)
            ->and(ChannelShare::of(350000, 10000))->toBe(350000);
    });

    it('stays exact at the largest fee and share the API accepts (R$ 100.000,00 at 50 %)', function () {
        expect(ChannelShare::of(10_000_000, 5000))->toBe(5_000_000);
    });

    it('sums per firm after rounding each one, never on the summed fees', function () {
        // Three firms of R$ 0,10 at 15 %: 1,5 cent each rounds to 2, so 6 — the fees summed first would give 5 (4,5 → 5).
        expect(ChannelShare::sum([[10, 1500], [10, 1500], [10, 1500]]))->toBe(6)
            ->and(ChannelShare::sum([[10, 1500, 3]]))->toBe(6)
            ->and(ChannelShare::of(30, 1500))->toBe(5);
    });
});
