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

    /**
     * The person's specialist row in the firm, made or rewritten (one per person). Runs as that firm;
     * `firm_id` is written explicitly because seeders run without model events.
     *
     * @param  array{area: string|null, focus: string}  $attributes
     */
    public function upsertSpecialist(int $firmId, int $userId, array $attributes): Specialist
    {
        return Specialist::query()->updateOrCreate(['user_id' => $userId], [...$attributes, 'firm_id' => $firmId]);
    }

    /**
     * Replaces the specialist's weekly hours with these (`weekday`, `opens_at`, `closes_at`).
     *
     * @param  list<array{weekday: int, opens_at: string, closes_at: string}>  $hours
     */
    public function replaceSpecialistHours(Specialist $specialist, array $hours): void
    {
        $specialist->hours()->delete();
        $specialist->hours()->createMany($hours);
    }
}
