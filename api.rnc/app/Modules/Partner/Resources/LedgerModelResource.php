<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The network's money model as it is now (rnc-2 R11), the answer of `PATCH ledger/model`: the
 * model and both rules — the rate in basis points and the monthly fee in cents.
 *
 * @property Firm $resource
 */
class LedgerModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'model' => $this->resource->partnerMoneyModel()->value,
            'commission_rate_bp' => $this->resource->partner_commission_rate_bp,
            'monthly_fee_cents' => $this->resource->partner_monthly_fee_cents,
        ];
    }
}
