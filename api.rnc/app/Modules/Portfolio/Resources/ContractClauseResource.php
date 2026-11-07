<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\Models\ClientContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contract after "Cláusula incluída" (rnc-3 R22): the clause, when (UTC ISO-8601) and by whom.
 *
 * @mixin ClientContract
 */
class ContractClauseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'has_reforma_clause' => (bool) $this->has_reforma_clause,
            // A class cast keeps the zone it was set with: write the instant in UTC (Assistant pitfall).
            'clause_marked_at' => $this->clause_marked_at?->utc()->toIso8601String(),
            'clause_marked_by' => $this->clause_marked_by,
        ];
    }
}
