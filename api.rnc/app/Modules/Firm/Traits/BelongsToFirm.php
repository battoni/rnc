<?php

namespace App\Modules\Firm\Traits;

use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Scopes\FirmScope;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every business model uses this (D1): reads see only the current firm's rows, and a new row is
 * stamped with the current firm unless it names one itself.
 *
 * A row of another firm is therefore not "forbidden" but absent — `findOrFail` throws and the API
 * answers 404, the same as for an id that never existed (`arcus-api-architecture`, "Rows a person
 * owns"). Reaching across firms is `withoutGlobalScope(FirmScope::class)`, and only a queued job
 * that received the model itself may do it (plan AM6).
 *
 * @mixin Model
 */
trait BelongsToFirm
{
    public static function bootBelongsToFirm(): void
    {
        static::addGlobalScope(new FirmScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('firm_id') !== null) {
                return;
            }

            $model->setAttribute('firm_id', app(CurrentFirm::class)->id());
        });
    }

    /** @return BelongsTo<Firm, $this> */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }
}
