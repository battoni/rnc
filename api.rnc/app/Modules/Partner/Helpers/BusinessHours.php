<?php

namespace App\Modules\Partner\Helpers;

use App\Modules\Booking\Helpers\Holidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A firm's working week, the SLA's clock (rnc-2 R8): the firm's `business_hours` — a list of
 * `{weekdays, from, to}` (ISO weekdays, 1 = Monday; Franco's seg–qui 8–18, sex 8–17) — read in
 * `America/Sao_Paulo`, minus Booking's national holidays. Pure maths: the caller hands it the week.
 * The demo's `businessHoursBetween` / `minusBusinessHours` (+ the seed's `after`), with the holidays
 * it lacked.
 */
class BusinessHours
{
    public const ZONE = 'America/Sao_Paulo';

    private const SECONDS_PER_HOUR = 3600;

    /** How far `before()` and `after()` look for open hours — two months, the demo's 60 days. */
    private const LOOKBACK_DAYS = 60;

    /** @var array<int, list<array{0: string, 1: string}>> ISO weekday → its `[from, to]` windows */
    private array $windows = [];

    /** @param  list<array{weekdays: list<int>, from: string, to: string}>  $week */
    public function __construct(array $week)
    {
        foreach ($week as $hours) {
            foreach ($hours['weekdays'] as $weekday) {
                $this->windows[(int) $weekday][] = [$hours['from'], $hours['to']];
            }
        }
    }

    /** Business hours from `$from` to `$to`, to the second; zero when `$to` is not after `$from`. */
    public function between(CarbonInterface $from, CarbonInterface $to): float
    {
        $start = CarbonImmutable::instance($from)->setTimezone(self::ZONE);
        $end = CarbonImmutable::instance($to)->setTimezone(self::ZONE);
        $seconds = 0;

        for ($day = $start->startOfDay(); $day < $end; $day = $day->addDay()) {
            foreach ($this->windowsOf($day) as [$opens, $closes]) {
                $seconds += max(0, $closes->min($end)->getTimestamp() - $opens->max($start)->getTimestamp());
            }
        }

        return $seconds / self::SECONDS_PER_HOUR;
    }

    /** The moment `$hours` business hours before `$to` — how the seed dates its open referrals. */
    public function before(CarbonInterface $to, float $hours): CarbonImmutable
    {
        $end = CarbonImmutable::instance($to)->setTimezone(self::ZONE);
        $left = (int) round($hours * self::SECONDS_PER_HOUR);
        $day = $end->startOfDay();

        for ($i = 0; $i < self::LOOKBACK_DAYS; $i++, $day = $day->subDay()) {
            foreach (array_reverse($this->windowsOf($day)) as [$opens, $closes]) {
                $closes = $closes->min($end);
                $open = $closes->getTimestamp() - $opens->getTimestamp();

                if ($open <= 0) {
                    continue;
                }

                if ($open >= $left) {
                    return $closes->subSeconds($left);
                }

                $left -= $open;
            }
        }

        return $end->subSeconds($left);
    }

    /** The moment `$hours` business hours after `$from` — how the seed dates a first contact. */
    public function after(CarbonInterface $from, float $hours): CarbonImmutable
    {
        $start = CarbonImmutable::instance($from)->setTimezone(self::ZONE);
        $left = (int) round($hours * self::SECONDS_PER_HOUR);
        $day = $start->startOfDay();

        for ($i = 0; $i < self::LOOKBACK_DAYS; $i++, $day = $day->addDay()) {
            foreach ($this->windowsOf($day) as [$opens, $closes]) {
                $opens = $opens->max($start);
                $open = $closes->getTimestamp() - $opens->getTimestamp();

                if ($open <= 0) {
                    continue;
                }

                if ($open >= $left) {
                    return $opens->addSeconds($left);
                }

                $left -= $open;
            }
        }

        return $start->addSeconds($left);
    }

    /** @return list<array{0: CarbonImmutable, 1: CarbonImmutable}> the day's open windows; none on a holiday */
    private function windowsOf(CarbonImmutable $day): array
    {
        if (Holidays::isHoliday($day)) {
            return [];
        }

        return array_map(
            fn (array $window) => [$day->setTimeFromTimeString($window[0]), $day->setTimeFromTimeString($window[1])],
            $this->windows[$day->dayOfWeekIso] ?? [],
        );
    }
}
