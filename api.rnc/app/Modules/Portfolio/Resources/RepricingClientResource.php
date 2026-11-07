<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\DTOs\ClientQuoteDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the repricing list (R26): the client's profile, today's fee, the suggested one, the
 * uplift and, once marked, the mark with who did it. Money in integer cents.
 *
 * @property ClientQuoteDTO $resource
 */
class RepricingClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $client = $this->resource->client;
        $quote = $this->resource->quote;
        $repricing = $client->repricing;

        return [
            'id' => $client->id,
            'name' => $client->name,
            'cnpj' => $client->cnpj,
            'sector' => $client->sector,
            'regime' => $client->regime,
            'band' => $client->band,
            'b2b_share' => $client->b2b_share,
            'current_cents' => self::cents($quote['current']),
            'suggested_cents' => self::cents($quote['suggested']),
            'pct' => $quote['pct'],
            'uplift_cents' => self::cents($quote['uplift']),
            'opportunities' => $quote['opportunities'],
            'risks' => $quote['risks'],
            'repricing' => $repricing === null ? null : [
                'at' => $repricing['at'],
                'by' => (int) $repricing['by'],
                'by_name' => $this->resource->markedByName,
                'pct' => (int) $repricing['pct'],
                'suggested_cents' => self::cents((float) $repricing['suggested']),
            ],
        ];
    }

    /** Money travels as integer cents (the SPA's `formatReal`); the table keeps reais. */
    public static function cents(float $reais): int
    {
        return (int) round($reais * 100);
    }
}
