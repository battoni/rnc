<?php

namespace App\Modules\Booking\Helpers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Brazil's national holidays, the days a specialist's agenda skips (rnc-1 R15, plan AM9): a
 * closed list — the fixed dates and the four that move with Easter. No state or city holiday.
 */
class Holidays
{
    /** Month-day of the fixed national holidays. */
    private const FIXED = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];

    /** Days from Easter Sunday: Carnaval Monday and Tuesday, Good Friday, Corpus Christi. */
    private const EASTER_OFFSETS = [-48, -47, -2, 60];

    /** @var array<int, list<string>> */
    private static array $years = [];

    public static function isHoliday(CarbonInterface $date): bool
    {
        return in_array($date->format('Y-m-d'), self::forYear((int) $date->format('Y')), true);
    }

    /** @return list<string> every holiday of the year as `Y-m-d`, in date order */
    public static function forYear(int $year): array
    {
        if (isset(self::$years[$year])) {
            return self::$years[$year];
        }

        $easter = self::easter($year);
        $dates = [
            ...array_map(fn (string $monthDay) => "{$year}-{$monthDay}", self::FIXED),
            ...array_map(fn (int $offset) => $easter->addDays($offset)->toDateString(), self::EASTER_OFFSETS),
        ];

        sort($dates);

        return self::$years[$year] = $dates;
    }

    /** Easter Sunday by the anonymous Gregorian algorithm (Meeus/Jones/Butcher). */
    public static function easter(int $year): CarbonImmutable
    {
        $golden = $year % 19;
        $century = intdiv($year, 100);
        $yearOfCentury = $year % 100;
        $leapCenturies = intdiv($century, 4);
        $centuryRemainder = $century % 4;
        $correction = intdiv($century + 8, 25);
        $moonCorrection = intdiv($century - $correction + 1, 3);
        $epact = (19 * $golden + $century - $leapCenturies - $moonCorrection + 15) % 30;
        $leapYears = intdiv($yearOfCentury, 4);
        $yearRemainder = $yearOfCentury % 4;
        $weekday = (32 + 2 * $centuryRemainder + 2 * $leapYears - $epact - $yearRemainder) % 7;
        $shift = intdiv($golden + 11 * $epact + 22 * $weekday, 451);
        $month = intdiv($epact + $weekday - 7 * $shift + 114, 31);
        $day = (($epact + $weekday - 7 * $shift + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
