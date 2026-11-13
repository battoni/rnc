<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\DTOs\ClientQuoteDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the letter of repricing prints (R26): the client, today's and the suggested fee, the items
 * that justify it, and the firm that signs it with its public contact.
 *
 * @property array{quote: ClientQuoteDTO, firm: Firm, issued_on: string, effective_from: string} $resource
 */
class RepricingLetterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quote = $this->resource['quote'];
        $firm = $this->resource['firm'];

        return [
            'client' => ['id' => $quote->client->id, 'name' => $quote->client->name, 'cnpj' => $quote->client->cnpj],
            'current_cents' => RepricingClientResource::cents($quote->quote['current']),
            'suggested_cents' => RepricingClientResource::cents($quote->quote['suggested']),
            'pct' => $quote->quote['pct'],
            'items' => array_map(fn (array $item) => ['kind' => $item['kind'], 'title' => $item['title']], $quote->quote['items']),
            'firm' => ['name' => $firm->name, 'address' => $firm->address, 'phone' => $firm->phone, 'mailbox' => $firm->mailbox],
            'issued_on' => $this->resource['issued_on'],
            'effective_from' => $this->resource['effective_from'],
        ];
    }
}
