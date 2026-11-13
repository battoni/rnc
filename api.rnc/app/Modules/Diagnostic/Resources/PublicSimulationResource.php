<?php

namespace App\Modules\Diagnostic\Resources;

use App\Modules\Diagnostic\Enums\Tax;
use App\Modules\Diagnostic\Services\RulesEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The impact simulator (rnc-1 R14): one entry per year 2026–2033, the legend, and the split
 * payment's monthly range. `estimate` is always true — every figure is shown as "estimativa".
 *
 * @property array{years: list<array{year: int, parts: list<array{tax: string, label: string, rate: float}>, total: float}>, split_payment: array{0: float, 1: float}} $resource
 */
class PublicSimulationResource extends JsonResource
{
    private const MONTH_UNIT = 'mês';

    public function toArray(Request $request): array
    {
        [$low, $high] = $this->resource['split_payment'];

        return [
            'years' => $this->resource['years'],
            'taxes' => array_map(fn (Tax $tax) => ['tax' => $tax->value, 'label' => $tax->label(), 'leaving' => $tax->isLeaving()], Tax::cases()),
            'split_payment' => [
                'min' => (int) round($low),
                'max' => (int) round($high),
                'label' => RulesEngine::formatRange($low, $high, self::MONTH_UNIT),
            ],
            'estimate' => true,
        ];
    }
}
