<?php

namespace App\Modules\Fiscal\Repositories;

use App\Modules\Fiscal\Enums\NfeImportSource;
use App\Modules\Fiscal\Models\NfeImport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The firm's NF-e import batches. `BelongsToFirm` stamps and scopes the current firm. The list reads
 * who imported from Auth's `users` (`by_name`, read only, one join — no query per row).
 */
class NfeImportRepository
{
    public function createNfeImport(int $firmId, NfeImportSource $source, ?int $userId, CarbonImmutable $at): NfeImport
    {
        return NfeImport::query()->create([
            'firm_id' => $firmId,
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

    /** @return LengthAwarePaginator<int, NfeImport> newest first */
    public function paginateNfeImports(int $perPage): LengthAwarePaginator
    {
        return $this->listQuery()->orderByDesc('nfe_imports.at')->orderByDesc('nfe_imports.id')->paginate($perPage);
    }

    public function findNfeImportForList(int $id): ?NfeImport
    {
        return $this->listQuery()->where('nfe_imports.id', $id)->first();
    }

    /** @return Builder<NfeImport> */
    private function listQuery(): Builder
    {
        return NfeImport::query()
            ->leftJoin('users', 'users.id', '=', 'nfe_imports.user_id')
            ->select('nfe_imports.*', 'users.name as by_name');
    }
}
