<?php

namespace App\Modules\Firm\Scopes;

use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Only the current firm's rows (D1).
 *
 * Closed when no firm is set: a query outside a request and outside `CurrentFirm::runAs()` reads
 * nothing instead of everything. An empty `whereIn` compiles to a false condition on every driver,
 * so the closed answer is the same on MySQL and on the SQLite the tests run on (AM11).
 */
class FirmScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $firmId = app(CurrentFirm::class)->id();
        $column = $model->qualifyColumn('firm_id');

        if ($firmId === null) {
            $builder->whereIn($column, []);

            return;
        }

        $builder->where($column, $firmId);
    }
}
