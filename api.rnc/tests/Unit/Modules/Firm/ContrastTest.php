<?php

use App\Modules\Firm\Helpers\Contrast;

/*
 * D4 (rnc-3 R5): white text on a brand's primary must reach WCAG AA, 4,5:1. The ratio is the
 * WCAG 2 relative-luminance formula; the boundary pair is #767676 (4,54:1, passes) and #777777
 * (4,48:1, fails), the grey every contrast checker uses to show the line.
 */
describe('Contrast', function () {
    it('answers 21:1 for white on black and 1:1 for white on white', function () {
        expect(Contrast::ratio('#ffffff', '#000000'))->toBe(21.0)
            ->and(Contrast::ratio('#ffffff', '#ffffff'))->toBe(1.0);
    });

    it('is symmetric and case-blind', function () {
        expect(Contrast::ratio('#00375A', '#FFFFFF'))->toBe(Contrast::ratio('#ffffff', '#00375a'));
    });

    it('draws the AA line between #767676 and #777777', function () {
        expect(Contrast::whiteOn('#767676'))->toBeGreaterThanOrEqual(Contrast::AA_MINIMUM)
            ->and(Contrast::whiteOn('#777777'))->toBeLessThan(Contrast::AA_MINIMUM)
            ->and(round(Contrast::whiteOn('#767676'), 2))->toBe(4.54)
            ->and(round(Contrast::whiteOn('#777777'), 2))->toBe(4.48);
    });

    it("passes Franco's navy and the Express and Prime primaries, refuses yellow", function () {
        expect(Contrast::whiteOn('#00375a'))->toBeGreaterThan(Contrast::AA_MINIMUM)
            ->and(Contrast::whiteOn('#3b2a86'))->toBeGreaterThan(Contrast::AA_MINIMUM)
            ->and(Contrast::whiteOn('#24262b'))->toBeGreaterThan(Contrast::AA_MINIMUM)
            ->and(Contrast::whiteOn('#ffff00'))->toBeLessThan(Contrast::AA_MINIMUM);
    });
});
