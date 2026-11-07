<?php

namespace App\Modules\Portfolio\DTOs;

use App\Modules\Portfolio\Models\Client;

/**
 * A client with its repricing quote (`RepricingCalculator::quote`) and, once marked, the name of
 * who marked it. What every repricing resource reads.
 */
final readonly class ClientQuoteDTO
{
    /**
     * @param  array{items: list<array{id: string, kind: string, title: string, rule: string, detail: string, min: float, max: float}>, opportunities: int, risks: int, current: float, suggested: float, pct: int, uplift: float}  $quote
     */
    public function __construct(
        public Client $client,
        public array $quote,
        public ?string $markedByName = null,
    ) {}
}
