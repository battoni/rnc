<?php

namespace App\Modules\Radar\Resources;

use App\Modules\Portfolio\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One client of a Radar type (rnc-2 R25): its cadastro (the DECLARED B2B share), its partner consent
 * (R28: true · false · null), the item's range in cents (a year, or held per month for
 * `split-payment`), the proof (`source` `notas` · `cadastro`, "do cadastro") and up to three access
 * keys when the proof is the XMLs. Never the raw XML nor its path.
 *
 * @property array{client: Client, range_cents: array{0: int, 1: int}, evidence: array<string, mixed>, keys: list<string>} $resource
 */
class RadarClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $client = $this->resource['client'];

        return [
            'id' => (int) $client->id,
            'name' => $client->name,
            'cnpj' => $client->cnpj,
            'sector' => $client->sector,
            'regime' => $client->regime,
            'band' => $client->band,
            'b2b_share' => $client->b2b_share,
            'partner_consent' => $client->partner_consent,
            'range_cents' => $this->resource['range_cents'],
            'evidence' => $this->resource['evidence'],
            'keys' => $this->resource['keys'],
        ];
    }
}
