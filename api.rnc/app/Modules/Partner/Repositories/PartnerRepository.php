<?php

namespace App\Modules\Partner\Repositories;

use App\Modules\Partner\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

/** The firm's partners. `BelongsToFirm` keeps every query inside the current firm. */
class PartnerRepository
{
    /** @return Collection<int, Partner> active first, then by name */
    public function findAllPartners(): Collection
    {
        return Partner::query()->with('account')->orderByDesc('active')->orderBy('name')->get();
    }

    /** @return Collection<int, Partner> */
    public function findActivePartners(): Collection
    {
        return Partner::query()->where('active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Partner> by name, inactive too */
    public function findPartnersByName(): Collection
    {
        return Partner::query()->orderBy('name')->get();
    }

    public function findPartnerById(int $id): ?Partner
    {
        return Partner::query()->find($id);
    }

    /** The portal's partner (rnc-2 R4): of the current firm, and active. */
    public function findActivePartnerById(int $id): ?Partner
    {
        return Partner::query()->where('active', true)->find($id);
    }

    /** @param  array<string, mixed>  $attributes */
    public function createPartner(array $attributes): Partner
    {
        return Partner::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updatePartner(Partner $partner, array $attributes): Partner
    {
        $partner->update($attributes);

        return $partner;
    }
}
