<?php

namespace App\Modules\Content\Repositories;

use App\Modules\Content\Models\SectorPage;
use Illuminate\Database\Eloquent\Collection;

class SectorPageRepository
{
    /** @return Collection<int, SectorPage> */
    public function findAllSectorPages(): Collection
    {
        return SectorPage::query()->orderBy('position')->orderBy('id')->get();
    }

    public function findSectorPageBySlug(string $slug): ?SectorPage
    {
        return SectorPage::query()->where('slug', $slug)->first();
    }

    /**
     * Writes a firm's page by slug, in place when it exists — re-running never duplicates a page.
     * Runs as that firm (`CurrentFirm::runAs`), so the scoped lookup sees its rows; `firm_id` is
     * written explicitly because seeders run without model events.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertSectorPage(int $firmId, string $slug, array $attributes): SectorPage
    {
        return SectorPage::query()->where('firm_id', $firmId)->updateOrCreate(['slug' => $slug], [...$attributes, 'firm_id' => $firmId]);
    }
}
