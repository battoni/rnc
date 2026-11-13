<?php

use App\Modules\Booking\Helpers\Holidays;
use Carbon\CarbonImmutable;

/*
 * The national holidays a specialist's agenda skips (rnc-1 R15, plan AM9): a closed list of fixed
 * dates plus the four that move with Easter (Meeus's algorithm).
 */
it('finds Easter by Meeus', function (int $year, string $easter) {
    expect(Holidays::easter($year)->toDateString())->toBe($easter);
})->with([
    [2026, '2026-04-05'],
    [2027, '2027-03-28'],
    [2028, '2028-04-16'],
    [2030, '2030-04-21'],
]);

it('lists the fixed and the Easter-based holidays of a year, in date order', function () {
    expect(Holidays::forYear(2026))->toBe([
        '2026-01-01',
        '2026-02-16',
        '2026-02-17',
        '2026-04-03',
        '2026-04-21',
        '2026-05-01',
        '2026-06-04',
        '2026-09-07',
        '2026-10-12',
        '2026-11-02',
        '2026-11-15',
        '2026-11-20',
        '2026-12-25',
    ]);
});

it('answers whether a day is a holiday, whatever the time of day', function () {
    expect(Holidays::isHoliday(CarbonImmutable::parse('2026-11-20 15:30', 'America/Sao_Paulo')))->toBeTrue()
        ->and(Holidays::isHoliday(CarbonImmutable::parse('2027-02-08', 'America/Sao_Paulo')))->toBeTrue()
        ->and(Holidays::isHoliday(CarbonImmutable::parse('2026-11-19', 'America/Sao_Paulo')))->toBeFalse()
        ->and(Holidays::isHoliday(CarbonImmutable::parse('2026-12-24', 'America/Sao_Paulo')))->toBeFalse();
});

it('places the four Easter-based holidays by their offsets (Easter 2027 is 28/03)', function () {
    expect(array_values(array_diff(Holidays::forYear(2027), ['2027-01-01', '2027-04-21', '2027-05-01', '2027-09-07', '2027-10-12', '2027-11-02', '2027-11-15', '2027-11-20', '2027-12-25'])))->toBe([
        '2027-02-08', // Carnaval Monday, Easter − 48
        '2027-02-09', // Carnaval Tuesday, Easter − 47
        '2027-03-26', // Good Friday, Easter − 2
        '2027-05-27', // Corpus Christi, Easter + 60
    ]);
});
