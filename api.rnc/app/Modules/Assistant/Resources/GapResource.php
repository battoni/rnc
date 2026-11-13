<?php

namespace App\Modules\Assistant\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of "Perguntas sem nota" (rnc-3 R15): the normalised text, one of its wordings, how many
 * times it was asked in the last 30 days and when last.
 *
 * @property array{normalised: string, question: string, count: int, last_asked_at: CarbonImmutable} $resource
 */
class GapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'normalised' => $this->resource['normalised'],
            'question' => $this->resource['question'],
            'count' => $this->resource['count'],
            'last_asked_at' => $this->resource['last_asked_at']->toIso8601String(),
        ];
    }
}
