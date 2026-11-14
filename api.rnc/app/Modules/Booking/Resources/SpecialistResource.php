<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bookable specialist on the manager's settings screen (rnc-4 R32): who, the area shown and the
 * weekly hours in reading order.
 *
 * @mixin Specialist
 */
class SpecialistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => (int) $this->user_id,
            'name' => $this->displayName(),
            'area' => $this->area,
            'hours' => SpecialistHourResource::collection(SpecialistHourResource::inWeekOrder($this->hours)),
        ];
    }
}
