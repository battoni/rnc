<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Enums\SpecialistFocus;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Models\SpecialistHour;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Database\Seeder;

/**
 * The bookable people of every firm (rnc-1 R15): each user flagged `is_specialist` gets a specialist
 * row and, as weekly hours, the firm's business hours (Franco: seg–qui 8–18, sex 8–17). The area and
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
    ];

    public function run(CurrentFirm $currentFirm): void
    {
        Firm::query()->orderBy('id')->each(fn (Firm $firm) => $currentFirm->runAs($firm, fn () => $this->seedFirm($firm)));
    }

    private function seedFirm(Firm $firm): void
    {
        $people = User::query()->where('firm_id', $firm->id)->where('is_specialist', true)->orderBy('id')->get();

        foreach ($people as $person) {
            $profile = self::PROFILES[$person->username] ?? null;

            $specialist = Specialist::query()->updateOrCreate(
                ['user_id' => $person->id],
                ['firm_id' => $firm->id, 'area' => $profile['area'] ?? null, 'focus' => ($profile['focus'] ?? SpecialistFocus::Geral)->value],
            );

            SpecialistHour::query()->where('specialist_id', $specialist->id)->delete();

            foreach ($firm->business_hours ?? [] as $hours) {
                foreach ($hours['weekdays'] as $weekday) {
                    SpecialistHour::query()->create([
                        'specialist_id' => $specialist->id,
                        'weekday' => $weekday,
                        'opens_at' => $hours['from'],
                        'closes_at' => $hours['to'],
                    ]);
                }
            }
        }
    }
}
