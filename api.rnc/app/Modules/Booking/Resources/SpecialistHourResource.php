<?php

namespace App\Modules\Booking\Resources;

use App\Modules\Booking\Models\SpecialistHour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One window of a specialist's week (rnc-4 R32): ISO weekday and `HH:MM` local times.
 *
 * @mixin SpecialistHour
 */
class SpecialistHourResource extends JsonResource
{
    /**
     * The week in reading order: by weekday, then by opening time.
     *
     * @param  Collection<int, SpecialistHour>  $hours
     * @return Collection<int, SpecialistHour>
     */
    public static function inWeekOrder(Collection $hours): Collection
    {
        return $hours->sortBy([['weekday', 'asc'], ['opens_at', 'asc']])->values();
    }

    public function toArray(Request $request): array
    {
        return [
            'weekday' => (int) $this->weekday,
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
        ];
    }
}
