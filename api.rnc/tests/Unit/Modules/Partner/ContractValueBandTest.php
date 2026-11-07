<?php

use App\Modules\Partner\Helpers\ContractValueBand;

/*
 * rnc-3 AM20 (R21, A19): a contract's annual value as the partner sees it — a fixed band, lower
 * bound included, upper excluded, never the value itself.
 */
it('puts a value in its fixed band, the lower bound included', function (int $cents, array $band) {
    expect(ContractValueBand::of($cents))->toBe($band);
})->with([
    'R$ 40 mil' => [4_000_000, ['min_cents' => 0, 'max_cents' => 10_000_000, 'label' => 'até R$ 100 mil']],
    'R$ 99.999,99' => [9_999_999, ['min_cents' => 0, 'max_cents' => 10_000_000, 'label' => 'até R$ 100 mil']],
    'R$ 100 mil' => [10_000_000, ['min_cents' => 10_000_000, 'max_cents' => 25_000_000, 'label' => 'R$ 100 mil a R$ 250 mil']],
    'R$ 250 mil' => [25_000_000, ['min_cents' => 25_000_000, 'max_cents' => 50_000_000, 'label' => 'R$ 250 mil a R$ 500 mil']],
    'R$ 734 mil' => [73_456_789, ['min_cents' => 50_000_000, 'max_cents' => 100_000_000, 'label' => 'R$ 500 mil a R$ 1 mi']],
    'R$ 1 mi' => [100_000_000, ['min_cents' => 100_000_000, 'max_cents' => 200_000_000, 'label' => 'R$ 1 mi a R$ 2 mi']],
    'R$ 1,2 mi' => [120_000_000, ['min_cents' => 100_000_000, 'max_cents' => 200_000_000, 'label' => 'R$ 1 mi a R$ 2 mi']],
    'R$ 2 mi' => [200_000_000, ['min_cents' => 200_000_000, 'max_cents' => null, 'label' => 'acima de R$ 2 mi']],
    'R$ 9 mi' => [900_000_000, ['min_cents' => 200_000_000, 'max_cents' => null, 'label' => 'acima de R$ 2 mi']],
]);
