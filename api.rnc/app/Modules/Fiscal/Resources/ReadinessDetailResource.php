<?php

namespace App\Modules\Fiscal\Resources;

use App\Modules\Fiscal\Services\ReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One client of "Prontidão da NF-e" opened (R19): its row, the issue lines — each with how many
 * invoices, whether it is the 1115 risk, and up to five sample invoices `{number, series, item}`
 * newest first — and the window in days.
 *
 * @phpstan-import-type ReadinessRow from ReadinessService
 * @phpstan-import-type ReadinessIssueLine from ReadinessService
 *
 * @property array{row: ReadinessRow, issues: list<ReadinessIssueLine>} $resource
 */
class ReadinessDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new ReadinessClientResource($this->resource['row']))->toArray($request),
            'issues' => $this->resource['issues'],
            'window_days' => ReadinessService::WINDOW_DAYS,
        ];
    }
}
