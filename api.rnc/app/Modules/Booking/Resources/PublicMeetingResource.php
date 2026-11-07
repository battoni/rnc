<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Services\BookingCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A meeting as the visitor sees it: who, which area, when — ISO-8601 with the São Paulo offset.
 *
 * @mixin Appointment
 */
class PublicMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'specialist' => $this->specialist->displayName(),
            'area' => $this->specialist->area,
            'starts_at' => BookingCalendar::localIso($this->starts_at),
            'ends_at' => BookingCalendar::localIso($this->ends_at),
        ];
    }
}
