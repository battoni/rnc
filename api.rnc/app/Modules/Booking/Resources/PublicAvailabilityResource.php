<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Services\BookingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The slot picker's data (rnc-1 R15) — an allow-list: per specialist only the id, the name, the
 * area and the free starts, ISO-8601 with the São Paulo offset (AM9). Never the person's e-mail.
 *
 * @property array{suggested_specialist_id: int|null, specialists: list<array{specialist: Specialist, suggested: bool, days: list<array{date: string, weekday: int, slots: list<CarbonImmutable>}>}>} $resource
 */
class PublicAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'suggested_specialist_id' => $this->resource['suggested_specialist_id'],
            'slot_minutes' => BookingCalendar::SLOT_MINUTES,
            'timezone' => BookingCalendar::TIMEZONE,
            'specialists' => array_map(fn (array $entry) => [
                'id' => $entry['specialist']->id,
                'name' => $entry['specialist']->displayName(),
                'area' => $entry['specialist']->area,
                'suggested' => $entry['suggested'],
                'days' => array_map(fn (array $day) => [
                    'date' => $day['date'],
                    'weekday' => $day['weekday'],
                    'slots' => array_map(fn (CarbonImmutable $slot) => BookingCalendar::localIso($slot), $day['slots']),
                ], $entry['days']),
            ], $this->resource['specialists']),
        ];
    }
}
