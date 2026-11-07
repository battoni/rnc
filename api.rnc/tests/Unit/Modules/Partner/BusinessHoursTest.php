<?php

use App\Modules\Partner\Helpers\BusinessHours;
use Carbon\CarbonImmutable;

/*
 * Franco's working week (rnc-2 R8): the firm's `business_hours` — seg–qui 8–18, sex 8–17 — in
 * `America/Sao_Paulo`, minus Booking's national holidays. The SLA clock counts only these hours.
 *
 * 13/11/2026 is a Friday; 20/11/2026 (Consciência Negra) a national holiday, also a Friday.
 */
const FRANCO_WEEK = [
    ['weekdays' => [1, 2, 3, 4], 'from' => '08:00', 'to' => '18:00'],
    ['weekdays' => [5], 'from' => '08:00', 'to' => '17:00'],
];

function hoursAt(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'America/Sao_Paulo');
}

function francoHours(): BusinessHours
{
    return new BusinessHours(FRANCO_WEEK);
}

it('skips the weekend: Friday 16:00 to Monday 8:00 is one hour (A7)', function () {
    expect(francoHours()->between(hoursAt('2026-11-13 16:00'), hoursAt('2026-11-16 08:00')))->toBe(1.0);
});

it('counts the days\' own lengths: Friday to 17, Monday to 18', function () {
    expect(francoHours()->between(hoursAt('2026-11-13 08:00'), hoursAt('2026-11-16 18:00')))->toBe(19.0);
});

it('counts a holiday as zero (A7)', function () {
    expect(francoHours()->between(hoursAt('2026-11-19 16:00'), hoursAt('2026-11-23 08:00')))->toBe(2.0)
        ->and(francoHours()->between(hoursAt('2026-11-20 09:00'), hoursAt('2026-11-20 16:00')))->toBe(0.0);
});

it('counts fractions of an hour', function () {
    expect(francoHours()->between(hoursAt('2026-11-16 09:15'), hoursAt('2026-11-16 10:00')))->toBe(0.75);
});

it('starts a referral sent out of hours at the next opening', function () {
    expect(francoHours()->between(hoursAt('2026-11-14 11:00'), hoursAt('2026-11-16 09:00')))->toBe(1.0)
        ->and(francoHours()->between(hoursAt('2026-11-16 20:00'), hoursAt('2026-11-17 08:30')))->toBe(0.5);
});

it('reads UTC moments in São Paulo time', function () {
    expect(francoHours()->between(CarbonImmutable::parse('2026-11-16T11:00:00Z'), CarbonImmutable::parse('2026-11-16T13:00:00Z')))->toBe(2.0);
});

it('answers zero when the end is not after the start, or the firm has no hours', function () {
    expect(francoHours()->between(hoursAt('2026-11-16 12:00'), hoursAt('2026-11-16 10:00')))->toBe(0.0)
        ->and((new BusinessHours([]))->between(hoursAt('2026-11-16 08:00'), hoursAt('2026-11-20 08:00')))->toBe(0.0);
});

it('walks back a number of business hours, the seed\'s clock', function () {
    expect(francoHours()->before(hoursAt('2026-11-16 09:00'), 3)->format('Y-m-d H:i'))->toBe('2026-11-13 15:00')
        ->and(francoHours()->before(hoursAt('2026-11-23 10:00'), 15)->format('Y-m-d H:i'))->toBe('2026-11-18 15:00')
        ->and(francoHours()->before(hoursAt('2026-11-17 12:00'), 4)->format('Y-m-d H:i'))->toBe('2026-11-17 08:00');
});

it('walks forward a number of business hours, the seed\'s first contact', function () {
    expect(francoHours()->after(hoursAt('2026-11-13 16:00'), 3)->format('Y-m-d H:i'))->toBe('2026-11-16 10:00')
        ->and(francoHours()->after(hoursAt('2026-11-19 05:00'), 12)->format('Y-m-d H:i'))->toBe('2026-11-23 10:00')
        ->and(francoHours()->after(hoursAt('2026-11-17 08:00'), 10)->format('Y-m-d H:i'))->toBe('2026-11-17 18:00');
});

it('walks back across the holiday as if it were a weekend day', function () {
    $from = francoHours()->before(hoursAt('2026-11-23 10:00'), 15);

    expect(francoHours()->between($from, hoursAt('2026-11-23 10:00')))->toBe(15.0);
});
