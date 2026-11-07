<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Models\SectorPage;
use App\Modules\Content\Repositories\SectorPageRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * The current firm's sector pages. The firm comes from `FirmScope`, so a slug of another firm reads
 * as absent and the controller answers 404.
 */
class SectorPageService
{
    public function __construct(private SectorPageRepository $sectorPageRepository) {}

    /** @return Collection<int, SectorPage> */
    public function list(): Collection
    {
        return $this->sectorPageRepository->findAllSectorPages();
    }

    public function findBySlug(string $slug): ?SectorPage
    {
        return $this->sectorPageRepository->findSectorPageBySlug($slug);
    }
}
