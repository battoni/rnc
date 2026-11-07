<?php

namespace App\Modules\Diagnostic\Resources;

use App\Modules\Diagnostic\Services\RulesEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The result as the visitor sees it (rnc-1 R13) — an allow-list: per item only the kind, the title
 * and the R$ range. The detail and the cited rule stay with the team: they are what the meeting is
 * for, and a public route never sends them.
 *
 * @property array{company: string|null, opportunities: int, risks: int, items: list<array{kind: string, title: string, min: float, max: float}>} $resource
 */
class PublicDiagnosticResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company' => $this->resource['company'],
            'opportunities' => $this->resource['opportunities'],
            'risks' => $this->resource['risks'],
            'items' => array_map(fn (array $item) => [
                'kind' => $item['kind'],
                'title' => $item['title'],
                'range_min' => (int) round($item['min']),
                'range_max' => (int) round($item['max']),
                'range_label' => RulesEngine::formatRange($item['min'], $item['max']),
            ], $this->resource['items']),
        ];
    }
}
