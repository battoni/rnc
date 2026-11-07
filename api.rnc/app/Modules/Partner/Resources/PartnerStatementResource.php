<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Firm\Enums\PartnerMoneyModel;
use App\Modules\Partner\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The partner's "Extrato" (rnc-2 R11, D10): its month under the firm's model only — the rule of
 * that model (the rate under comissão, the fee under assinatura), never the other model's total
 * nor any other partner's line. Moments are UTC ISO-8601; money is integer cents.
 *
 * @phpstan-import-type PartnerStatement from LedgerService
 *
 * @property PartnerStatement $resource
 */
class PartnerStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statement = $this->resource;
        $rule = $statement['model'] === PartnerMoneyModel::Comissao
            ? ['commission_rate_bp' => $statement['commission_rate_bp']]
            : ['monthly_fee_cents' => $statement['monthly_fee_cents']];

        return [
            'month' => $statement['month'],
            'model' => $statement['model']->value,
            'firm' => ['name' => $statement['firm_name']],
            ...$rule,
            'lines' => array_map(fn (array $line) => [
                ...$line,
                'closed_at' => $line['closed_at']?->utc()->toIso8601String(),
            ], $statement['lines']),
            'deals' => $statement['deals'],
            'deal_total_cents' => $statement['deal_total_cents'],
            'total_cents' => $statement['total_cents'],
        ];
    }
}
