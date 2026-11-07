<?php

namespace App\Modules\Metrics\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The funnel (R24): the window, the seven steps with the drop-off from the step before, and the
 * tables by source and by sector page. Counts only — no visitor, no lead, no contact.
 *
 * @property array{days: int, from: CarbonImmutable, to: CarbonImmutable, steps: list<array{key: string, count: int, drop_off: float|null}>, by_source: list<array<string, int|string>>, by_sector: list<array<string, int|string>>} $resource
 */
class FunnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'days' => $this->resource['days'],
            'from' => $this->resource['from']->toIso8601String(),
            'to' => $this->resource['to']->toIso8601String(),
            'steps' => $this->resource['steps'],
            'by_source' => $this->resource['by_source'],
            'by_sector' => $this->resource['by_sector'],
        ];
    }
}
