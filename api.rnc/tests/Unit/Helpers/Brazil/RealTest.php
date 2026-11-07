<?php

use App\Helpers\Brazil\Real;

it('formats cents as pt-BR currency', function () {
    expect(Real::format(123456))->toBe('R$ 1.234,56')
        ->and(Real::format(0))->toBe('R$ 0,00')
        ->and(Real::format(500))->toBe('R$ 5,00')
        ->and(Real::format(123456789))->toBe('R$ 1.234.567,89');
});
