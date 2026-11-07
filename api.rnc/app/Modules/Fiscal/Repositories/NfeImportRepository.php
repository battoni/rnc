<?php

namespace App\Modules\Fiscal\Repositories;

use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Models\NfeImport;
use Carbon\CarbonImmutable;

/** The firm's NF-e import batches. `BelongsToFirm` stamps and scopes the current firm. */
class NfeImportRepository
{
    public function createNfeImport(NfeImportSource $source, ?int $userId, CarbonImmutable $at): NfeImport
    {
        return NfeImport::query()->create([
            'user_id' => $userId,
            'source' => $source->value,
            'files' => 0,
            'stored' => 0,
            'refused' => [],
            'at' => $at,
        ]);
    }

    /** @param  list<array{file: string, code: string}>  $refused */
    public function updateNfeImportTotals(NfeImport $import, int $files, int $stored, array $refused): NfeImport
    {
        $import->forceFill(['files' => $files, 'stored' => $stored, 'refused' => $refused])->save();

        return $import;
    }
}
