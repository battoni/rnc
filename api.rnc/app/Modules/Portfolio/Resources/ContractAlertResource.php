<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\Helpers\ContractExposure;
use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the contract alert (rnc-3 R19): the client, the contract and its exposure in the
 * 2027–2033 transition. Money in integer cents; the SPA writes the sentence and the chips.
 *
 * @mixin ClientContract
 */
class ContractAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'client' => ['id' => (int) $this->client->id, 'name' => $this->client->name],
            'kind' => $this->kind,
            'annual_value_cents' => (int) $this->annual_value_cents,
            'start_year' => (int) $this->start_year,
            'end_year' => (int) $this->end_year,
            'years_exposed' => ContractExposure::years($this->start_year, $this->end_year),
            'exposure_cents' => ContractExposure::cents($this->annual_value_cents, $this->start_year, $this->end_year),
        ];
    }
}
