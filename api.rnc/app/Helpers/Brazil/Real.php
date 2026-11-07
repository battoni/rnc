<?php

namespace App\Helpers\Brazil;

class Real
{
    private const CENTS_PER_UNIT = 100;

    /**
     * "R$ 1.234,56" — pt-BR grouping, always two decimals. Takes cents, never a float:
     * the arithmetic is `App\Helpers\Money`'s, and this only speaks it to a person.
     */
    public static function format(int $cents): string
    {
        return 'R$ '.number_format($cents / self::CENTS_PER_UNIT, 2, ',', '.');
    }
}
