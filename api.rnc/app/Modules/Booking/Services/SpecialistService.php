<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Booking\Enums\SpecialistFocus;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Repositories\SpecialistRepository;
use App\Modules\Firm\Models\Firm;
use Illuminate\Database\Eloquent\Collection;

/**
 * Makes a person bookable (rnc-1 R15): their specialist row and, as weekly hours, the firm's business
 * hours. `SpecialistSeeder` and `firm:create --specialist` (rnc-4 F3b1, R21) both come through here.
 * The caller runs it as that firm (`CurrentFirm::runAs`).
 */
class SpecialistService
{
    public function __construct(private SpecialistRepository $specialistRepository) {}

    public function makeBookable(User $person, Firm $firm, ?string $area = null, SpecialistFocus $focus = SpecialistFocus::Geral): Specialist
    {
        $specialist = $this->specialistRepository->upsertSpecialist($firm->id, $person->id, ['area' => $area, 'focus' => $focus->value]);

        $hours = [];

        foreach ($firm->business_hours ?? [] as $window) {
            foreach ($window['weekdays'] as $weekday) {
                $hours[] = ['weekday' => $weekday, 'opens_at' => $window['from'], 'closes_at' => $window['to']];
            }
        }

        $this->specialistRepository->replaceSpecialistHours($specialist, $hours);

        return $specialist;
    }

    /**
     * A person the manager just flagged a specialist (rnc-4 F5a, R32): bookable on the firm's
     * business hours — unless it was one before, whose own hours are kept as they were.
     */
    public function startOnFirmHours(User $person, Firm $firm): Specialist
    {
        return $this->specialistRepository->findSpecialistByUserId($person->id) ?? $this->makeBookable($person, $firm);
    }

    /** @return Collection<int, Specialist> the firm's bookable specialists with their hours (the settings screen) */
    public function list(): Collection
    {
        return $this->specialistRepository->findAllSpecialistsWithHours();
    }

    /** One bookable specialist of the current firm with its hours; null for another firm's or none. */
    public function find(int $id): ?Specialist
    {
        return $this->specialistRepository->findSpecialistWithHoursById($id);
    }

    /**
     * Replaces a specialist's weekly hours (rnc-4 R32); the visitor's availability reads them on its
     * next call. Null for another firm's specialist, or none.
     *
     * @param  list<array{weekday: int, opens_at: string, closes_at: string}>  $hours
     */
    public function updateHours(int $id, array $hours): ?Specialist
    {
        $specialist = $this->specialistRepository->findSpecialistWithHoursById($id);

        if (! $specialist) {
            return null;
        }

        $this->specialistRepository->replaceSpecialistHours($specialist, $hours);

        return $specialist->load('hours');
    }
}
