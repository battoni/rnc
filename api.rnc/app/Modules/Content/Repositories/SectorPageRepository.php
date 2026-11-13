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
}
