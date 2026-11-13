<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Services\BookingCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The agenda's week (R20): its bounds, the weeks around it, and per working day each meeting —
 * the lead it is for, its stage, the specialist, the times in São Paulo (AM9).
 *
 * @property array{week_start: string, week_end: string, previous_week: string, next_week: string, days: list<array{date: string, meetings: list<Appointment>}>} $resource
 */
class AgendaWeekResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'week_start' => $this->resource['week_start'],
            'week_end' => $this->resource['week_end'],
            'previous_week' => $this->resource['previous_week'],
            'next_week' => $this->resource['next_week'],
            'days' => array_map(fn (array $day) => [
                'date' => $day['date'],
                'meetings' => array_map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'lead_id' => (int) $appointment->lead_id,
                    'company' => $appointment->lead->company,
                    'stage' => $appointment->lead->stage,
                    'specialist' => $appointment->specialist->displayName(),
                    'area' => $appointment->specialist->area,
                    'starts_at' => BookingCalendar::localIso($appointment->starts_at),
                    'ends_at' => BookingCalendar::localIso($appointment->ends_at),
                ], $day['meetings']),
            ], $this->resource['days']),
        ];
    }
}
