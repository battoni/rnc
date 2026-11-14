<?php

namespace App\Modules\Booking\Repositories;

use App\Modules\Booking\Models\Specialist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every read is firm-scoped by `BelongsToFirm`: another firm's specialist is simply not found. A
 * bookable specialist is a row whose person is still flagged `is_specialist` (rnc-4 F5a, R31): the
 * manager's switch off takes the person out of the agenda, and the row — its hours, its past
 * appointments — stays for the day the switch goes back on.
 */
class SpecialistRepository
{
    /** @return Collection<int, Specialist> the current firm's bookable specialists, with the person and the hours */
    public function findAllSpecialistsWithHours(): Collection
    {
        return $this->bookable()->with(['user', 'hours'])->orderBy('id')->get();
    }

    public function findSpecialistWithHoursById(int $id): ?Specialist
    {
        return $this->bookable()->with(['user', 'hours'])->find($id);
    }

    /** The person's specialist row in the current firm, flagged or not; null when it never had one. */
    public function findSpecialistByUserId(int $userId): ?Specialist
    {
        return Specialist::query()->where('user_id', $userId)->first();
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

    /** @return Builder<Specialist> */
    private function bookable(): Builder
    {
        return Specialist::query()->whereHas('user', fn (Builder $query) => $query->where('is_specialist', true));
    }
}
