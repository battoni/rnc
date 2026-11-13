<?php

namespace App\Modules\Fiscal\Services;

use App\Modules\Fiscal\Models\NfeImport;
use App\Modules\Fiscal\Repositories\NfeImportRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/** The firm's import batches as the screen reads them (R17): who by name, newest first. */
class NfeImportService
{
    public function __construct(private NfeImportRepository $nfeImportRepository) {}

    /** @return LengthAwarePaginator<int, NfeImport> */
    public function batches(int $perPage): LengthAwarePaginator
    {
        return $this->nfeImportRepository->paginateNfeImports($perPage);
    }

    /** One batch just written, read back the way the list shows it. */
    public function batch(NfeImport $import): NfeImport
    {
        return $this->nfeImportRepository->findNfeImportForList($import->id) ?? $import;
    }
}
