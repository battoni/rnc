<?php

namespace App\Modules\Firm\Repositories;

use App\Modules\Firm\Models\Firm;

class FirmRepository
{
    public function findFirmById(int $id): ?Firm
    {
        return Firm::query()->find($id);
    }

    public function findFirmBySlug(string $slug): ?Firm
    {
        return Firm::query()->where('slug', $slug)->first();
    }

    /**
     * The firm the root host serves and a person with no firm works in (rnc-4 D3, plan AM1). One row
     * at most; the lowest id wins should a second ever be flagged.
     */
    public function findPlatformFirm(): ?Firm
    {
        return Firm::query()->where('is_platform', true)->orderBy('id')->first();
    }

    /** @param  array<string, mixed>  $attributes */
    public function createFirm(array $attributes): Firm
    {
        return Firm::query()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    public function updateFirm(Firm $firm, array $attributes): Firm
    {
        $firm->update($attributes);

        return $firm;
    }

    /** @param  list<string>  $modules  the opt-in keys, already checked by `FirmModulesService` */
    public function updateFirmModules(Firm $firm, array $modules): Firm
    {
        $firm->forceFill(['modules' => $modules])->save();

        return $firm;
    }
}
