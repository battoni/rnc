<?php

use App\Helpers\Money;

describe('Money', function () {
    it('converts floats to integer cents', function () {
        expect(Money::toCents(180.5))->toBe(18050)
            ->and(Money::toCents(220))->toBe(22000)
            ->and(Money::toCents(33.33))->toBe(3333)
            ->and(Money::toCents(0.1 + 0.2))->toBe(30);
    });

    it('takes an integer percentage rounded half up', function () {
        expect(Money::percentOf(88000, 10.0))->toBe(8800)
            ->and(Money::percentOf(9999, 10.0))->toBe(1000)
            ->and(Money::percentOf(22000, 25.0))->toBe(5500)
            ->and(Money::percentOf(5, 10.0))->toBe(1)
            ->and(Money::percentOf(4, 10.0))->toBe(0)
            ->and(Money::percentOf(100, 12.5))->toBe(13)
            ->and(Money::percentOf(0, 10.0))->toBe(0);
    });

    it('rounds a negative share away from zero, symmetric with the positive one', function () {
        expect(Money::percentOf(-5, 10.0))->toBe(-1)
            ->and(Money::percentOf(-4, 10.0))->toBe(0);
    });
});
