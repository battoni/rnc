<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Franco's "Extrato da rede" (rnc-2 R11): both rules, every partner's month — closings, the
 * commission, whether its access billed the fee, and what it owes under the current model — the
 * total, and the other model's total. Money is integer cents.
 *
 * @phpstan-import-type NetworkStatement from LedgerService
 *
 * @property NetworkStatement $resource
 */
class NetworkStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statement = $this->resource;

        return [
            'month' => $statement['month'],
            'model' => $statement['model']->value,
            'commission_rate_bp' => $statement['commission_rate_bp'],
            'monthly_fee_cents' => $statement['monthly_fee_cents'],
            'partners' => array_map(fn (array $row) => [
                'partner' => [
                    'id' => $row['partner']->id,
                    'name' => $row['partner']->name,
                    'specialty' => $row['partner']->specialty,
                    'active' => (bool) $row['partner']->active,
                ],
                'deals' => $row['deals'],
                'deal_total_cents' => $row['deal_total_cents'],
                'commission_cents' => $row['commission_cents'],
                'subscribed' => $row['subscribed'],
                'subscription_cents' => $row['subscription_cents'],
                'due_cents' => $row['due_cents'],
            ], $statement['partners']),
            'deals' => $statement['deals'],
            'deal_total_cents' => $statement['deal_total_cents'],
            'total_cents' => $statement['total_cents'],
            'other' => ['model' => $statement['other_model']->value, 'total_cents' => $statement['other_total_cents']],
        ];
    }
}
