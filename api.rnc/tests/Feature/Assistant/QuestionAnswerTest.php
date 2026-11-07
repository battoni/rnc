<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Assistant\Repositories\AssistantQuestionRepository;
use App\Modules\Firm\Models\Firm;
use App\Modules\Notification\Notifications\AssistantAnswerNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;

/*
 * "Perguntas para especialista" (rnc-3 R14, A13's answer half):
 *
 *   GET  /api/assistant/questions                the specialist's own; every one with assistant.manage
 *   POST /api/assistant/questions/{id}/answer    {answer} — the assigned specialist only, else 404
 *   POST /api/assistant/notes                    {…, from_question} — "Virar nota": a rascunho
 *
 * The answer closes the question (respondida) and rings the asker's bell. Another specialist, Reinaldo
 * and another firm's specialist all get 404: the question is not theirs to answer.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->patricia = User::where('username', 'patricia')->firstOrFail();
    $this->mariana = User::where('username', 'mariana.toledo')->firstOrFail();
    $this->carlos = User::where('username', 'carlos.prado')->firstOrFail();
    $this->reinaldo = User::where('username', 'reinaldo')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** Patrícia hands a question to `$specialist` through the API, and the hand-off's bell is cleared. */
function handedQuestion(User $specialist, string $question = 'Qual o melhor ERP para o Simples?'): AssistantQuestion
{
    $id = test()->actingAs(User::where('username', 'patricia')->firstOrFail())
        ->postJson('/api/assistant/questions', ['question' => $question, 'specialist_id' => $specialist->id])
        ->assertCreated()
        ->json('data.id');
    DatabaseNotification::query()->delete();

    return AssistantQuestion::withoutGlobalScopes()->findOrFail($id);
}

/** @return array<string, mixed> a complete note turned from a question */
function noteFromQuestionBody(int $questionId, array $overrides = []): array
{
    return [
        'question' => 'Qual o melhor ERP para o Simples?',
        'answer' => 'Depende do volume de notas; a Franco indica dois parceiros.',
        'rule' => 'LC 123/2006',
        'source_label' => 'Jettax — Simples Nacional na Reforma',
        'source_url' => 'https://jettax.com.br/blog/simples-nacional-reforma-tributaria',
        'as_of' => '2026-11-05',
        'keywords_strong' => ['erp'],
        'keywords_weak' => ['simples'],
        'from_question' => $questionId,
        ...$overrides,
    ];
}

describe('the inbox (R14)', function () {
    it('shows a specialist only the questions handed to them, with the asker', function () {
        $mine = handedQuestion($this->mariana);
        handedQuestion($this->carlos, 'Outra pergunta sobre o split?');

        $rows = $this->actingAs($this->mariana)->getJson('/api/assistant/questions')->assertOk()->json('data');

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['id'])->toBe($mine->id)
            ->and($rows[0]['status'])->toBe('aberta')
            ->and($rows[0]['asked_by']['name'])->toBe('Patrícia Gomes')
            ->and($rows[0]['specialist']['name'])->toBe('Mariana Toledo')
            ->and($rows[0]['answer'])->toBeNull()
            ->and($rows[0]['note_id'])->toBeNull();
    });

    it('shows assistant.manage every question of the firm, open ones first', function () {
        $open = handedQuestion($this->carlos, 'Outra pergunta sobre o split?');
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour());
        $answered = handedQuestion($this->mariana);
        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$answered->id}/answer", ['answer' => 'Respondida depois.'])->assertOk();
        AssistantQuestion::withoutGlobalScopes()->forceCreate([
            'firm_id' => Firm::factory()->create(['slug' => 'firma-b'])->id,
            'asked_by' => $this->patricia->id,
            'specialist_id' => $this->carlos->id,
            'question' => 'De outra firma?',
            'status' => 'aberta',
        ]);

        $ids = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/questions')->assertOk()->json('data'))->pluck('id')->all();

        expect($ids)->toBe([$open->id, $answered->id]);
    });

    it('refuses with 403 assistant.notWriter a member who is neither specialist nor manager', function () {
        $this->actingAs($this->patricia)->getJson('/api/assistant/questions')
            ->assertForbidden()
            ->assertJsonPath('error', 'assistant.notWriter')
            ->assertJsonPath('code', 'not_writer');
    });
});

describe('the answer (A13)', function () {
    it("closes the question and rings the asker's bell, only theirs", function () {
        $question = handedQuestion($this->mariana);

        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => '  Depende do volume de notas.  '])
            ->assertOk()
            ->assertJsonPath('data.status', 'respondida')
            ->assertJsonPath('data.answer', 'Depende do volume de notas.')
            ->assertJsonPath('data.answered_at', CarbonImmutable::now()->utc()->toIso8601String());

        $bell = DatabaseNotification::sole();

        expect($question->refresh()->status)->toBe('respondida')
            ->and($bell->notifiable_id)->toBe($this->patricia->id)
            ->and($bell->data)->toBe([
                'kind' => AssistantAnswerNotification::KIND,
                'question_id' => $question->id,
                'question' => 'Qual o melhor ERP para o Simples?',
                'answered_by' => 'Mariana Toledo',
            ]);
    });

    it('answers 404 to anyone but the assigned specialist, and rings nobody', function (Closure $who) {
        $question = handedQuestion($this->mariana);

        $this->actingAs($who())->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => 'Não é minha.'])
            ->assertNotFound()
            ->assertJsonPath('error', 'assistant.questionNotFound');

        expect($question->refresh()->status)->toBe('aberta')
            ->and(DatabaseNotification::count())->toBe(0);
    })->with([
        'another specialist' => [fn () => User::where('username', 'carlos.prado')->firstOrFail()],
        'Reinaldo (assistant.manage)' => [fn () => User::where('username', 'reinaldo')->firstOrFail()],
        'the asker' => [fn () => User::where('username', 'patricia')->firstOrFail()],
        "firm B's specialist" => [fn () => assistantPerson('equipe', specialist: true, firm: Firm::factory()->create(['slug' => 'firma-b']))],
    ]);

    it('answers 404 on an unknown question', function () {
        $this->actingAs($this->mariana)->postJson('/api/assistant/questions/9999/answer', ['answer' => 'Nada.'])->assertNotFound();
    });

    it('refuses a second answer with 422 assistant.answered', function () {
        $question = handedQuestion($this->mariana);
        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => 'Primeira.'])->assertOk();
        DatabaseNotification::query()->delete();

        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => 'Segunda.'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'assistant.answered')
            ->assertJsonPath('code', 'answered');

        expect($question->refresh()->answer)->toBe('Primeira.')
            ->and(DatabaseNotification::count())->toBe(0);
    });

    it('claims the answer in one conditional update, so a racing second answer never lands', function () {
        $question = handedQuestion($this->mariana);
        $repository = app(AssistantQuestionRepository::class);

        expect($repository->claimAnswer($question, 'Primeira.', CarbonImmutable::now()))->toBeTrue()
            ->and($repository->claimAnswer($question, 'Segunda.', CarbonImmutable::now()))->toBeFalse()
            ->and($question->refresh()->answer)->toBe('Primeira.');
    });

    it('takes an answer of 3 to 2000 characters', function () {
        $question = handedQuestion($this->mariana);

        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => ' a '])->assertUnprocessable()->assertJsonValidationErrors('answer');
        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('answer');
        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => str_repeat('a', 2000)])->assertOk();
    });
});

describe('"Virar nota" (A13)', function () {
    it('creates a rascunho with the question and marks the question as turned into it', function () {
        $question = handedQuestion($this->mariana);
        $this->actingAs($this->mariana)->postJson("/api/assistant/questions/{$question->id}/answer", ['answer' => 'Depende do volume de notas.'])->assertOk();

        $response = $this->actingAs($this->mariana)->postJson('/api/assistant/notes', noteFromQuestionBody($question->id))
            ->assertCreated()
            ->assertJsonPath('data.status', 'rascunho')
            ->assertJsonPath('data.question', 'Qual o melhor ERP para o Simples?')
            ->assertJsonPath('data.reviewer', null)
            ->assertJsonPath('data.last_edited_by.name', 'Mariana Toledo');

        $note = AssistantNote::withoutGlobalScopes()->findOrFail($response->json('data.id'));

        expect($note->firm_id)->toBe($this->franco->id)
            ->and($question->refresh()->note_id)->toBe($note->id)
            ->and($this->actingAs($this->mariana)->getJson('/api/assistant/questions')->json('data.0.note_id'))->toBe($note->id);
    });

    it('lets assistant.manage turn any question of the firm into a note', function () {
        $question = handedQuestion($this->carlos);

        $this->actingAs($this->reinaldo)->postJson('/api/assistant/notes', noteFromQuestionBody($question->id))->assertCreated();

        expect($question->refresh()->note_id)->not->toBeNull();
    });

    it('turns a question into one note only: the second is 422 assistant.alreadyNoted', function () {
        $question = handedQuestion($this->mariana);
        $this->actingAs($this->mariana)->postJson('/api/assistant/notes', noteFromQuestionBody($question->id))->assertCreated();

        $this->actingAs($this->mariana)->postJson('/api/assistant/notes', noteFromQuestionBody($question->id, ['question' => 'De novo?']))
            ->assertUnprocessable()
            ->assertJsonPath('error', 'assistant.alreadyNoted')
            ->assertJsonPath('code', 'already_noted');

        expect(AssistantNote::withoutGlobalScopes()->count())->toBe(1);
    });

    it("answers 404 on a question outside the writer's inbox, and writes no note", function (Closure $question) {
        $id = $question()->id;

        $this->actingAs($this->carlos)->postJson('/api/assistant/notes', noteFromQuestionBody($id))
            ->assertNotFound()
            ->assertJsonPath('error', 'assistant.questionNotFound');

        expect(AssistantNote::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        "Mariana's question" => [fn () => handedQuestion(User::where('username', 'mariana.toledo')->firstOrFail())],
        "firm B's question" => [fn () => AssistantQuestion::withoutGlobalScopes()->forceCreate([
            'firm_id' => Firm::factory()->create(['slug' => 'firma-b'])->id,
            'asked_by' => 1,
            'specialist_id' => User::where('username', 'carlos.prado')->value('id'),
            'question' => 'De outra firma?',
            'status' => 'aberta',
        ])],
    ]);

    it('still creates a plain note with no question behind it', function () {
        $body = noteFromQuestionBody(0);
        unset($body['from_question']);

        $this->actingAs($this->mariana)->postJson('/api/assistant/notes', $body)->assertCreated();
    });
});
