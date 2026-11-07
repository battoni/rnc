<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Helpers\Holidays;
use App\Modules\Booking\Models\Specialist;
use Carbon\CarbonImmutable;

/**
 * The agenda's arithmetic (rnc-1 R15, plan AM9), pure: the next 10 working days, today counted —
 * Monday to Friday, minus the national holidays — and a specialist's 30-minute slots in them, all
 * computed in São Paulo time. A slot that starts before closing but would end after it is not one,
 * and a slot less than 2 hours away is not offered (W57): today keeps only what is still bookable.
 */
class BookingCalendar
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public const SLOT_MINUTES = 30;

    private const WORKING_DAYS = 10;

    private const LAST_WORKING_WEEKDAY = 5;

    /** The notice a specialist gets: a slot starts at least this far from now. */
    public const LEAD_TIME_MINUTES = 120;

    /** @return list<CarbonImmutable> the days offered, each at local midnight */
    public function workingDays(CarbonImmutable $now): array
    {
        $days = [];
        $day = $now->setTimezone(self::TIMEZONE)->startOfDay();

        for (; count($days) < self::WORKING_DAYS; $day = $day->addDay()) {
            if ($day->isoWeekday() > self::LAST_WORKING_WEEKDAY || Holidays::isHoliday($day)) {
                continue;
            }

            $days[] = $day;
        }

        return $days;
    }

    /**
     * A specialist's free slots on each offered day.
     *
     * @param  list<CarbonImmutable>  $days
     * @param  list<int>  $bookedStarts  UTC timestamps already taken
     * @return list<array{date: string, weekday: int, slots: list<CarbonImmutable>}>
     */
    public function slotsOf(Specialist $specialist, CarbonImmutable $now, array $days, array $bookedStarts = []): array
    {
        $booked = array_flip($bookedStarts);

        return array_map(fn (CarbonImmutable $day) => [
            'date' => $day->toDateString(),
            'weekday' => $day->isoWeekday(),
            'slots' => array_values(array_filter(
                $this->slotsOfDay($specialist, $now, $day),
                fn (CarbonImmutable $slot) => ! isset($booked[$slot->getTimestamp()]),
            )),
        ], $days);
    }

    /** Whether the agenda offers this start at all, booked or not. */
    public function offers(Specialist $specialist, CarbonImmutable $now, CarbonImmutable $startsAt): bool
    {
        $local = $startsAt->setTimezone(self::TIMEZONE);
        $day = collect($this->workingDays($now))->first(fn (CarbonImmutable $offered) => $offered->isSameDay($local));

        if (! $day) {
            return false;
        }

        return collect($this->slotsOfDay($specialist, $now, $day))->contains(fn (CarbonImmutable $slot) => $slot->getTimestamp() === $startsAt->getTimestamp());
    }

    /** The moment as the visitor reads it: ISO-8601 with the São Paulo offset. */
    public static function localIso(CarbonImmutable $moment): string
    {
        return $moment->setTimezone(self::TIMEZONE)->toIso8601String();
    }

    /** @return list<CarbonImmutable> */
    private function slotsOfDay(Specialist $specialist, CarbonImmutable $now, CarbonImmutable $day): array
    {
        $slots = [];
        $earliest = $now->addMinutes(self::LEAD_TIME_MINUTES);

        foreach ($specialist->hours->where('weekday', $day->isoWeekday()) as $hours) {
            $slot = $day->setTimeFromTimeString($hours->opens_at);
            $closes = $day->setTimeFromTimeString($hours->closes_at);

            while ($slot->addMinutes(self::SLOT_MINUTES) <= $closes) {
                if ($slot >= $earliest) {
                    $slots[] = $slot;
                }

                $slot = $slot->addMinutes(self::SLOT_MINUTES);
            }
        }

        usort($slots, fn (CarbonImmutable $left, CarbonImmutable $right) => $left <=> $right);

        return $slots;
    }
}
