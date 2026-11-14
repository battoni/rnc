<?php

namespace App\Modules\Booking\Services;

use App\Models\User;
use App\Modules\Booking\Enums\SpecialistFocus;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Repositories\SpecialistRepository;
use App\Modules\Firm\Models\Firm;

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
}
