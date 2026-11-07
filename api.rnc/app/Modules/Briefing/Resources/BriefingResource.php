<?php

namespace App\Modules\Briefing\Resources;

use App\Modules\Briefing\Services\BriefingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET briefing` (rnc-3 R23–R25): `BriefingService::build()` as it is — already plain values, times
 * in São Paulo ISO 8601, money in integer cents, ranges `[lo, hi]` or null. The e-mail renders the
 * same array, so the screen and the 07:00 message never disagree.
 *
 * @phpstan-import-type Briefing from BriefingService
 */
class BriefingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Briefing $briefing */
        $briefing = $this->resource;

        return $briefing;
    }
}
