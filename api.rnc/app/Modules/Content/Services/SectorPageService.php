<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\Helpers\DefaultSectorPages;
use App\Modules\Content\Models\SectorPage;
use App\Modules\Content\Repositories\SectorPageRepository;
use App\Modules\Firm\Models\Firm;
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

    /**
     * Gives a firm the six default pages (`DefaultSectorPages`), in their order, rewriting any of
     * them it already has (rnc-4 R21). The caller runs it as that firm (`CurrentFirm::runAs`).
     *
     * @return list<SectorPage>
     */
    public function startFirm(Firm $firm): array
    {
        $pages = [];

        foreach (DefaultSectorPages::PAGES as $position => $page) {
            $pages[] = $this->sectorPageRepository->upsertSectorPage($firm->id, $page['slug'], [...$page, 'position' => $position]);
        }

        return $pages;
    }
}
