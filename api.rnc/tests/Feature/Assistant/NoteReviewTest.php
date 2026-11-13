<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Firm\Models\Firm;
use Carbon\CarbonImmutable;
use Database\Seeders\AssistantNoteSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The knowledge base (rnc-3 R10, R11, D7, D8, AM4, AM7, AM19):
 *
 *   GET   /api/assistant/notes               every note of the firm, with its review state
 *   POST  /api/assistant/notes               a new note, as rascunho
 *   PATCH /api/assistant/notes/{id}          a content change sends it back to rascunho
 *   POST  /api/assistant/notes/{id}/review   revisada, by a specialist who is not the last editor
 *   POST  /api/assistant/notes/{id}/retire   aposentada (`assistant.manage`)
 *
 * Reading and writing it is `assistant.manage` (the ceo, the operator) or a specialist; reviewing is a
 * specialist who did not write the last change (four eyes, W10). A review is served for 90 days, day
 * 90 included; on day 91 it lists as "vencida" and answers nobody until it is reviewed again.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, AssistantNoteSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->carlos = User::where('username', 'carlos.prado')->firstOrFail();
    $this->juliana = User::where('username', 'juliana.reis')->firstOrFail();
    $this->patricia = User::where('username', 'patricia')->firstOrFail();
    $this->reinaldo = User::where('username', 'reinaldo')->firstOrFail();
    $this->cbs = AssistantNote::withoutGlobalScopes()->where('slug', 'cbs')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function noteOf(string $slug): AssistantNote
{
    return AssistantNote::withoutGlobalScopes()->where('slug', $slug)->firstOrFail();
}

function servedSlug(User $user, string $question): ?string
{
    return test()->actingAs($user)->postJson('/api/assistant/ask', ['question' => $question])->assertOk()->json('data.note.slug');
}

/** @return array<string, mixed> a complete new note */
function newNoteBody(array $overrides = []): array
{
    return [
        'question' => 'Como fica o IPI na Reforma?',
        'answer' => 'O IPI tem alíquota zerada a partir de 2027, salvo para produtos da Zona Franca de Manaus.',
        'rule' => 'EC 132/2023',
        'source_label' => 'gov.br/fazenda — Reforma Tributária',
        'source_url' => 'https://www.gov.br/fazenda/pt-br/acesso-a-informacao/acoes-e-programas/reforma-tributaria',
        'as_of' => '2026-11-05',
        'keywords_strong' => ['ipi'],
        'keywords_weak' => ['manaus', 'zona'],
        ...$overrides,
    ];
}

describe('the seed (R29, AM2, AM10, AM19, W12)', function () {
    it("seeds the demo's ten notes: credito-simples a draft, the others reviewed 1 to 5 days ago, read on 05/11/2026", function () {
        $notes = AssistantNote::withoutGlobalScopes()->orderBy('id')->get();
        $now = CarbonImmutable::now();

        expect($notes->pluck('slug')->all())->toBe(['cbs', 'icms', 'simples', 'credito-simples', 'split', 'rejeicao', 'reducao-60', 'reducao-30', 'aliquota', 'cashback'])
            ->and($notes->pluck('firm_id')->unique()->all())->toBe([$this->franco->id])
            ->and($notes->every(fn (AssistantNote $note) => $note->as_of->toDateString() === '2026-11-05'))->toBeTrue()
            ->and($notes->every(fn (AssistantNote $note) => str_starts_with($note->source_url, 'https://')))->toBeTrue()
            ->and(noteOf('credito-simples')->status)->toBe('rascunho')
            ->and(noteOf('credito-simples')->reviewed_at)->toBeNull();

        $reviewed = $notes->where('slug', '!=', 'credito-simples');

        expect($reviewed->pluck('status')->unique()->all())->toBe(['revisada'])
            ->and($reviewed->every(fn (AssistantNote $note) => ($days = (int) round($note->reviewed_at->diffInDays($now))) >= 1 && $days <= 5))->toBeTrue();
    });

    it('has the demo specialists review and Reinaldo write: nobody outside the seeded staff (AM10)', function () {
        $staff = ['mariana.toledo', 'carlos.prado', 'juliana.reis', 'rafael.antunes', 'reinaldo'];
        $notes = AssistantNote::withoutGlobalScopes()->get();
        $people = User::whereIn('id', [...$notes->pluck('reviewer_id')->filter(), ...$notes->pluck('last_edited_by')])->pluck('username')->all();

        expect($notes->pluck('last_edited_by')->unique()->all())->toBe([$this->reinaldo->id])
            ->and(array_diff($people, $staff))->toBe([])
            ->and(User::find(noteOf('cbs')->reviewer_id)->username)->toBe('mariana.toledo')
            ->and(User::find(noteOf('split')->reviewer_id)->username)->toBe('rafael.antunes');
    });

    it('seeds two open questions from Patrícia, to Mariana and to Carlos (AM2)', function () {
        $questions = AssistantQuestion::withoutGlobalScopes()->orderBy('id')->get();

        expect($questions)->toHaveCount(2)
            ->and($questions->pluck('status')->unique()->all())->toBe(['aberta'])
            ->and($questions->pluck('asked_by')->unique()->all())->toBe([$this->patricia->id])
            ->and(User::whereIn('id', $questions->pluck('specialist_id'))->orderBy('name')->pluck('username')->all())->toBe(['carlos.prado', 'mariana.toledo']);
    });

    it('rewrites the same rows on a second run', function () {
        $this->seed(AssistantNoteSeeder::class);

        expect(AssistantNote::withoutGlobalScopes()->count())->toBe(10)
            ->and(AssistantQuestion::withoutGlobalScopes()->count())->toBe(2);
    });

    it('seeds nothing on production', function () {
        AssistantNote::withoutGlobalScopes()->delete();
        AssistantQuestion::withoutGlobalScopes()->delete();
        app()->detectEnvironment(fn () => 'production');

        app(AssistantNoteSeeder::class)->setContainer(app())->__invoke();

        expect(AssistantNote::withoutGlobalScopes()->count())->toBe(0)
            ->and(AssistantQuestion::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('editing sends a note back to rascunho (A10, D7)', function () {
    it('returns a reviewed note to rascunho when its answer changes, and stops serving it', function () {
        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$this->cbs->id}", ['answer' => 'Texto novo, ainda não revisado.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rascunho')
            ->assertJsonPath('data.review_state', 'rascunho')
            ->assertJsonPath('data.last_edited_by.name', 'Juliana Reis')
            ->assertJsonPath('data.reviewer', null);

        expect(servedSlug($this->patricia, 'quando começa a cobrança da CBS'))->toBeNull();
    });

    it('returns it to rascunho on a change of question, rule, source or keywords too', function (string $field, mixed $value) {
        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$this->cbs->id}", [$field => $value])
            ->assertOk()
            ->assertJsonPath('data.status', 'rascunho');
    })->with([
        'question' => ['question', 'Quando começa a CBS de verdade?'],
        'rule' => ['rule', 'LC 214/2025, art. 343'],
        'source_label' => ['source_label', 'Outra fonte'],
        'source_url' => ['source_url', 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm'],
        'as_of' => ['as_of', '2026-11-06'],
        'keywords_strong' => ['keywords_strong', ['cbs', 'contribuicao']],
        'keywords_weak' => ['keywords_weak', ['quando']],
    ]);

    it('keeps it reviewed when the edit changes nothing', function () {
        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$this->cbs->id}", ['answer' => $this->cbs->answer, 'keywords_strong' => ['cbs']])
            ->assertOk()
            ->assertJsonPath('data.status', 'revisada');

        expect(noteOf('cbs')->last_edited_by)->toBe($this->reinaldo->id);
    });

    it('lets Reinaldo (assistant.manage) write a new note, as rascunho, served by nobody yet', function () {
        $this->actingAs($this->reinaldo)->postJson('/api/assistant/notes', newNoteBody())
            ->assertCreated()
            ->assertJsonPath('data.status', 'rascunho')
            ->assertJsonPath('data.slug', 'como-fica-o-ipi-na-reforma')
            ->assertJsonPath('data.last_edited_by.name', 'Reinaldo Franco');

        expect(servedSlug($this->patricia, 'e o IPI?'))->toBeNull();
    });

    it('refuses a team member who is not a specialist the knowledge base with 403 assistant.notWriter', function () {
        $this->actingAs($this->patricia)->getJson('/api/assistant/notes')->assertForbidden()->assertJsonPath('error', 'assistant.notWriter')->assertJsonPath('code', 'not_writer');
        $this->actingAs($this->patricia)->postJson('/api/assistant/notes', newNoteBody())->assertForbidden()->assertJsonPath('code', 'not_writer');
        $this->actingAs($this->patricia)->patchJson("/api/assistant/notes/{$this->cbs->id}", ['answer' => 'x x x'])->assertForbidden()->assertJsonPath('code', 'not_writer');

        expect(noteOf('cbs')->status)->toBe('revisada')
            ->and(AssistantNote::withoutGlobalScopes()->count())->toBe(10);
    });

    it('holds the lengths: question 200, answer 2000, rule 200, source 160 and 300, at least one strong key', function (array $body, string $field) {
        $this->actingAs($this->juliana)->postJson('/api/assistant/notes', newNoteBody($body))->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'question 201' => [['question' => str_repeat('a', 201)], 'question'],
        'answer 2001' => [['answer' => str_repeat('a', 2001)], 'answer'],
        'rule 201' => [['rule' => str_repeat('a', 201)], 'rule'],
        'source label 161' => [['source_label' => str_repeat('a', 161)], 'source_label'],
        'source url not a url' => [['source_url' => 'fonte'], 'source_url'],
        'source url 301' => [['source_url' => 'https://exemplo.com.br/'.str_repeat('a', 280)], 'source_url'],
        'as_of in the future' => [['as_of' => '2026-11-20'], 'as_of'],
        'no strong key' => [['keywords_strong' => []], 'keywords_strong'],
        'a key of one letter' => [['keywords_weak' => ['a']], 'keywords_weak.0'],
    ]);

    it('takes an answer of exactly 2000 characters', function () {
        $this->actingAs($this->juliana)->postJson('/api/assistant/notes', newNoteBody(['answer' => str_repeat('a', 2000)]))->assertCreated();
    });

    it('gives two notes of the same question two slugs', function () {
        $this->actingAs($this->juliana)->postJson('/api/assistant/notes', newNoteBody())->assertCreated()->assertJsonPath('data.slug', 'como-fica-o-ipi-na-reforma');
        $this->actingAs($this->juliana)->postJson('/api/assistant/notes', newNoteBody())->assertCreated()->assertJsonPath('data.slug', 'como-fica-o-ipi-na-reforma-2');
    });
});

describe('reviewing is four eyes (A10, R11, W10)', function () {
    it('refuses a non-specialist with 403 assistant.notReviewer: the team and Reinaldo', function () {
        $draft = noteOf('credito-simples');

        foreach ([$this->patricia, $this->reinaldo] as $person) {
            $this->actingAs($person)->postJson("/api/assistant/notes/{$draft->id}/review")
                ->assertForbidden()
                ->assertJsonPath('error', 'assistant.notReviewer')
                ->assertJsonPath('code', 'not_reviewer');
        }

        expect(noteOf('credito-simples')->status)->toBe('rascunho');
    });

    it("refuses the note's last editor, even a specialist, and lets another specialist review it", function () {
        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$this->cbs->id}", ['answer' => 'Texto revisto pela Juliana.'])->assertOk();

        $this->actingAs($this->juliana)->postJson("/api/assistant/notes/{$this->cbs->id}/review")->assertForbidden()->assertJsonPath('code', 'not_reviewer');

        $this->actingAs($this->carlos)->postJson("/api/assistant/notes/{$this->cbs->id}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'revisada')
            ->assertJsonPath('data.review_state', 'revisada')
            ->assertJsonPath('data.reviewer.name', 'Carlos Prado')
            ->assertJsonPath('data.reviewed_at', CarbonImmutable::now()->utc()->toIso8601String());

        expect(servedSlug($this->patricia, 'quando começa a cobrança da CBS'))->toBe('cbs');
    });

    it('tells each person whether they may review the note', function () {
        $rows = collect($this->actingAs($this->juliana)->getJson('/api/assistant/notes')->assertOk()->json('data'))->keyBy('slug');
        $mine = $this->actingAs($this->juliana)->postJson('/api/assistant/notes', newNoteBody())->assertCreated()->json('data');

        expect($rows['credito-simples']['can_review'])->toBeTrue()
            ->and($mine['can_review'])->toBeFalse()
            ->and(collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))->pluck('can_review')->unique()->all())->toBe([false]);
    });

    it('lists every note of the firm, the draft too, with who reviewed and who wrote it', function () {
        $rows = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))->keyBy('slug');

        expect($rows)->toHaveCount(10)
            ->and($rows['credito-simples']['review_state'])->toBe('rascunho')
            ->and($rows['cbs']['reviewer']['name'])->toBe('Mariana Toledo')
            ->and($rows['cbs']['last_edited_by']['name'])->toBe('Reinaldo Franco')
            ->and($rows['cbs']['keywords_strong'])->toBe(['cbs']);
    });
});

describe('a review lasts 90 days (A10, R11, D8, AM7)', function () {
    beforeEach(function () {
        $this->dated = AssistantNote::factory()->create([
            'firm_id' => $this->franco->id,
            'slug' => 'datada',
            'question' => 'Nota datada?',
            'keywords_strong' => ['datadak'],
            'keywords_weak' => [],
            'reviewer_id' => $this->carlos->id,
            'reviewed_at' => CarbonImmutable::parse('2026-08-01 10:00', 'America/Sao_Paulo'),
        ]);
    });

    it('is still served on day 90, late in the day', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 23:30', 'America/Sao_Paulo'));

        expect(servedSlug($this->patricia, 'datadak'))->toBe('datada');

        $row = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))->firstWhere('slug', 'datada');

        expect($row['review_state'])->toBe('revisada');
    });

    it('is not served on day 91 and lists as vencida', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 00:30', 'America/Sao_Paulo'));

        expect(servedSlug($this->patricia, 'datadak'))->toBeNull();

        $row = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))->firstWhere('slug', 'datada');

        expect($row['status'])->toBe('revisada')
            ->and($row['review_state'])->toBe('vencida');
    });

    it('is served again once another specialist reviews it', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 09:00', 'America/Sao_Paulo'));

        $this->actingAs($this->juliana)->postJson("/api/assistant/notes/{$this->dated->id}/review")->assertOk()->assertJsonPath('data.review_state', 'revisada');

        expect(servedSlug($this->patricia, 'datadak'))->toBe('datada');
    });
});

describe('retiring (R10, AM4)', function () {
    it('lets Reinaldo retire a note, which then answers nobody and can be neither edited nor reviewed', function () {
        $this->actingAs($this->reinaldo)->postJson("/api/assistant/notes/{$this->cbs->id}/retire")
            ->assertOk()
            ->assertJsonPath('data.status', 'aposentada')
            ->assertJsonPath('data.review_state', 'aposentada');

        expect(servedSlug($this->patricia, 'quando começa a cobrança da CBS'))->toBeNull();

        $this->actingAs($this->carlos)->postJson("/api/assistant/notes/{$this->cbs->id}/review")->assertUnprocessable()->assertJsonPath('code', 'retired');
        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$this->cbs->id}", ['answer' => 'De volta?'])->assertUnprocessable()->assertJsonPath('code', 'retired');

        expect(noteOf('cbs')->status)->toBe('aposentada');
    });

    it('refuses a specialist the retire (assistant.manage only)', function () {
        $this->actingAs($this->carlos)->postJson("/api/assistant/notes/{$this->cbs->id}/retire")->assertForbidden();

        expect(noteOf('cbs')->status)->toBe('revisada');
    });
});

describe('another firm (A25)', function () {
    it("answers 404 on firm B's note for every write, and lists none of firm B's", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = AssistantNote::factory()->create(['firm_id' => $firmB->id, 'slug' => 'deles', 'status' => 'rascunho', 'reviewed_at' => null, 'reviewer_id' => null]);

        $this->actingAs($this->juliana)->patchJson("/api/assistant/notes/{$theirs->id}", ['answer' => 'roubada'])->assertNotFound()->assertJsonPath('error', 'assistant.notFound');
        $this->actingAs($this->carlos)->postJson("/api/assistant/notes/{$theirs->id}/review")->assertNotFound();
        $this->actingAs($this->reinaldo)->postJson("/api/assistant/notes/{$theirs->id}/retire")->assertNotFound();

        $slugs = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))->pluck('slug');

        expect($slugs)->toHaveCount(10)->not->toContain('deles')
            ->and(AssistantNote::withoutGlobalScopes()->findOrFail($theirs->id)->status)->toBe('rascunho');
    });
});
