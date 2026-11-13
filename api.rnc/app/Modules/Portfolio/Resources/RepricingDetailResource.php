<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\DTOs\ClientQuoteDTO;
use Illuminate\Http\Request;

/**
 * One client of the repricing (R26): its row plus the diagnostic items behind the uplift, details
 * unlocked (this is staff only, `repricing.manage`).
 *
 * @property ClientQuoteDTO $resource
 */
class RepricingDetailResource extends RepricingClientResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'items' => array_map(fn (array $item) => [
                'id' => $item['id'],
                'kind' => $item['kind'],
                'title' => $item['title'],
                'rule' => $item['rule'],
                'detail' => $item['detail'],
            ], $this->resource->quote['items']),
        ];
    }
}
