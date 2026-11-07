<?php

namespace App\Modules\Firm\Repositories;

use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Eloquent\Collection;

/** The current firm's sub-brands. `BelongsToFirm` keeps every query inside the current firm. */
class BrandRepository
{
    public function __construct(private CurrentFirm $currentFirm) {}

    /** @return Collection<int, FirmBrand> by name, inactive too */
    public function findAllBrands(): Collection
    {
        return FirmBrand::query()->orderBy('name')->orderBy('id')->get();
    }

    public function findBrandById(int $id): ?FirmBrand
    {
        return FirmBrand::query()->find($id);
    }

    /** Active or not: a deactivated brand's leads are still filtered by it. */
    public function findBrandBySlug(string $slug): ?FirmBrand
    {
        return FirmBrand::query()->where('slug', $slug)->first();
    }

    /**
     * What the public site may be shown under. The slug is a visitor's `X-Brand` header, so the
     * current firm is named here too, not left to `BelongsToFirm` alone (rnc-3 review A1): no firm
     * finds nothing, and another firm's slug is unknown even if the scope is ever lifted.
     */
    public function findActiveBrandBySlug(string $slug): ?FirmBrand
    {
        $firmId = $this->currentFirm->id();

        if ($firmId === null) {
            return null;
        }

        return FirmBrand::query()->where('firm_id', $firmId)->where('slug', $slug)->where('active', true)->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateBrand(FirmBrand $brand, array $attributes): FirmBrand
    {
        $brand->update($attributes);

        return $brand;
    }
}
