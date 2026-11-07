<?php

namespace App\Modules\Portfolio\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A client's contract (rnc-3 R17): what the contract alert ranks. One per client, kind, start and
 * end year. Firm-scoped, never public.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $client_id
 * @property string $kind `ContractKind`
 * @property int $annual_value_cents
 * @property int $start_year
 * @property int $end_year
 * @property bool $has_reforma_clause
 * @property CarbonImmutable|null $clause_marked_at UTC — "Cláusula incluída" (R22)
 * @property int|null $clause_marked_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Client $client
 */
#[Fillable(['firm_id', 'client_id', 'kind', 'annual_value_cents', 'start_year', 'end_year', 'has_reforma_clause', 'clause_marked_at', 'clause_marked_by'])]
class ClientContract extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'annual_value_cents' => 'integer',
            'start_year' => 'integer',
            'end_year' => 'integer',
            'has_reforma_clause' => 'boolean',
            'clause_marked_at' => UtcDateTime::class,
            'clause_marked_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isClauseMarked(): bool
    {
        return $this->clause_marked_at !== null;
    }
}
