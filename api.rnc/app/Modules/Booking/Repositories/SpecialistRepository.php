<?php

namespace App\Modules\Booking\Repositories;

use App\Modules\Booking\Models\Specialist;
use Illuminate\Database\Eloquent\Collection;

/** Every read is firm-scoped by `BelongsToFirm`: another firm's specialist is simply not found. */
class SpecialistRepository
{
    /** @return Collection<int, Specialist> the current firm's specialists, with the person and the hours */
    public function findAllSpecialistsWithHours(): Collection
    {
        return Specialist::query()->with(['user', 'hours'])->orderBy('id')->get();
    }

    public function findSpecialistWithHoursById(int $id): ?Specialist
    {
        return Specialist::query()->with(['user', 'hours'])->find($id);
    }
}
