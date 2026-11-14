<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Enums\SpecialistFocus;
use App\Modules\Booking\Services\SpecialistService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Seeder;

/**
 * The bookable people of every firm (rnc-1 R15): each user flagged `is_specialist` gets a specialist
 * row and, as weekly hours, the firm's business hours (Franco: seg–qui 8–18, sex 8–17) — through
 * `SpecialistService::makeBookable`, the same path as `firm:create --specialist` (rnc-4 F3b1). The area and
 * the focus are the demo's four invented specialists (battonidev-1 `seed.js`); anyone else is the
 * Reforma generalist with no area. Re-running rewrites the hours to the firm's.
 *
 * Runs inside `CurrentFirm::runAs` — `DatabaseSeeder` runs without model events, so the firm is
 * written explicitly and the scoped reads see that firm (state.md Pitfalls).
 */
class SpecialistSeeder extends Seeder
{
    /** @var array<string, array{area: string, focus: SpecialistFocus}> */
    private const PROFILES = [
        'mariana.toledo' => ['area' => 'Tributário · Reforma', 'focus' => SpecialistFocus::Geral],
        'carlos.prado' => ['area' => 'Tributário · Indústria', 'focus' => SpecialistFocus::Industria],
        'juliana.reis' => ['area' => 'Contábil · Simples Nacional', 'focus' => SpecialistFocus::Simples],
        'rafael.antunes' => ['area' => 'Planejamento tributário', 'focus' => SpecialistFocus::Real],
        // The demo firm Alvorada's two (rnc-4 F3b2, invented).
        'bruno.alvorada' => ['area' => 'Tributário · Reforma', 'focus' => SpecialistFocus::Geral],
        'camila.alvorada' => ['area' => 'Contábil · Simples Nacional', 'focus' => SpecialistFocus::Simples],
    ];

    public function run(CurrentFirm $currentFirm, SpecialistService $specialistService): void
    {
        Firm::query()->orderBy('id')->each(fn (Firm $firm) => $currentFirm->runAs($firm, fn () => $this->seedFirm($firm, $specialistService)));
    }

    private function seedFirm(Firm $firm, SpecialistService $specialistService): void
    {
        $people = User::query()->where('firm_id', $firm->id)->where('is_specialist', true)->orderBy('id')->get();

        foreach ($people as $person) {
            $profile = self::PROFILES[$person->username] ?? null;

            $specialistService->makeBookable($person, $firm, $profile['area'] ?? null, $profile['focus'] ?? SpecialistFocus::Geral);
        }
    }
}
