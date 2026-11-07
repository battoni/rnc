<?php

namespace App\Modules\Pipeline\Services;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Booking\Services\BookingCalendar;
use Carbon\CarbonImmutable;

/**
 * The team's agenda (rnc-1 R20): the working week — Monday to Friday in São Paulo (AM9) — that holds
 * the date asked, every booked meeting of the firm by day, and the weeks before and after for ◀ ▶.
 * Asked for no date it opens on this week, or on the coming one on a Saturday or a Sunday: the week
 * just gone is not what the team plans from.
 */
class AgendaService
{
    private const WORKING_DAYS = 5;

    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(private AppointmentRepository $appointmentRepository) {}

    /**
     * @return array{week_start: string, week_end: string, previous_week: string, next_week: string, days: list<array{date: string, meetings: list<Appointment>}>}
     */
    public function week(?string $date): array
    {
        $anchor = $date !== null ? CarbonImmutable::createFromFormat(self::DATE_FORMAT, $date, BookingCalendar::TIMEZONE) : null;
        $monday = $anchor ? $this->mondayOf($anchor) : $this->openingMonday();
        $saturday = $monday->addDays(self::WORKING_DAYS);

        $meetings = $this->appointmentRepository->findAppointmentsBetween($monday, $saturday)
            ->groupBy(fn (Appointment $appointment) => $appointment->starts_at->setTimezone(BookingCalendar::TIMEZONE)->format(self::DATE_FORMAT));

        $days = [];

        for ($offset = 0; $offset < self::WORKING_DAYS; $offset++) {
            $day = $monday->addDays($offset)->format(self::DATE_FORMAT);

            $days[] = ['date' => $day, 'meetings' => $meetings->get($day)?->values()->all() ?? []];
        }

        return [
            'week_start' => $monday->format(self::DATE_FORMAT),
            'week_end' => $monday->addDays(self::WORKING_DAYS - 1)->format(self::DATE_FORMAT),
            'previous_week' => $monday->subWeek()->format(self::DATE_FORMAT),
            'next_week' => $monday->addWeek()->format(self::DATE_FORMAT),
            'days' => $days,
        ];
    }

    private function mondayOf(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone(BookingCalendar::TIMEZONE)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    private function openingMonday(): CarbonImmutable
    {
        $today = CarbonImmutable::now(BookingCalendar::TIMEZONE);

        return $this->mondayOf($today->isWeekend() ? $today->next(CarbonImmutable::MONDAY) : $today);
    }
}
