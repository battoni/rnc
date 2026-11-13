<?php

namespace Database\Factories;

use App\Modules\Assistant\Models\AssistantNote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An invented note, revisada now (so served) unless the test says otherwise. Stamped with the current
 * firm by `BelongsToFirm` unless the test names one; the reviewer and the editor are left empty.
 *
 * @extends Factory<AssistantNote>
 */
class AssistantNoteFactory extends Factory
{
    protected $model = AssistantNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->lexify('nota????');

        return [
            'slug' => $key,
            'question' => "Pergunta de exemplo {$key}?",
            'answer' => 'Resposta de exemplo, inventada para o teste.',
            'rule' => 'LC 214/2025',
            'source_label' => 'gov.br/fazenda — Reforma Tributária',
            'source_url' => 'https://www.gov.br/fazenda/pt-br/acesso-a-informacao/acoes-e-programas/reforma-tributaria',
            'as_of' => '2026-11-05',
            'keywords_strong' => [$key],
            'keywords_weak' => [],
            'status' => 'revisada',
            'reviewer_id' => null,
            'reviewed_at' => CarbonImmutable::now(),
            'last_edited_by' => null,
        ];
    }
}
