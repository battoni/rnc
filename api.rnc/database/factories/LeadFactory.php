<?php

namespace Database\Factories;

use App\Modules\Lead\Enums\LeadChannel;
use App\Modules\Lead\Enums\LeadStage;
use App\Modules\Lead\Enums\LeadStep;
use App\Modules\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A lead parcial from the quiz, with a live token. Stamped with the current firm by `BelongsToFirm`
 * unless the test names one.
 *
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(48),
            'token_expires_at' => now()->addDays(30),
            'visitor_uuid' => fake()->uuid(),
            'stage' => LeadStage::LeadParcial->value,
            'last_step' => LeadStep::Perfil->value,
            'path' => 'quiz',
            'company' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => '5511988887777',
            'email' => fake()->unique()->safeEmail(),
            'profile' => ['sector' => 'servicos', 'regime' => 'simples', 'band' => null, 'b2b' => null, 'incentivos' => false, 'exporta' => false],
            'channel' => LeadChannel::Formulario->value,
            'source' => 'direto',
            'medium' => null,
        ];
    }
}
