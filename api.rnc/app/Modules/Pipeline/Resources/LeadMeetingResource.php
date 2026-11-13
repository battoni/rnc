<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Services\BookingCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead's booked meeting as the team sees it: who, which area, when — ISO-8601 with the São Paulo
 * offset (AM9).
 *
 * @mixin Appointment
 */
class LeadMeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'specialist' => $this->specialist->displayName(),
            'area' => $this->specialist->area,
            'starts_at' => BookingCalendar::localIso($this->starts_at),
            'ends_at' => BookingCalendar::localIso($this->ends_at),
        ];
    }
}
