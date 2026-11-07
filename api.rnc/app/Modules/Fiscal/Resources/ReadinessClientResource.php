<?php

namespace App\Modules\Fiscal\Resources;

use App\Modules\Fiscal\Services\ReadinessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of "Prontidão da NF-e" (R18): the client's profile, its most frequent emitting system, the
 * window's issued invoices, how many complete (and the %, one decimal, null with none), how many at
 * risk of rejection 1115, and the status. The detail (`ReadinessDetailResource`) adds the issues.
 *
 * @phpstan-import-type ReadinessRow from ReadinessService
 *
 * @property ReadinessRow $resource
 */
class ReadinessClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $client = $row['client'];

        return [
            'id' => (int) $client->id,
            'name' => $client->name,
            'cnpj' => $client->cnpj,
            'sector' => $client->sector,
            'regime' => $client->regime,
            'system' => $row['system'],
            'invoices' => $row['invoices'],
            'complete' => $row['complete'],
            'complete_pct' => $row['complete_pct'],
            'at_risk' => $row['at_risk'],
            'status' => $row['status']->value,
        ];
    }
}
