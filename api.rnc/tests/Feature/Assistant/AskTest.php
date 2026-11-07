<?php

use App\Models\User;
use App\Modules\Assistant\Contracts\ReformaAnswerer;
use App\Modules\Assistant\Models\AssistantAsk;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Firm\Models\Firm;
use Carbon\CarbonImmutable;
use Database\Seeders\AssistantNoteSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * The Reforma assistant's ask (rnc-3 R12, R13, R15, D9):
 *
 *   POST /api/assistant/ask            {question, note_id?} → the best served note of the firm, or none
 *   GET  /api/assistant/suggestions    the served notes' questions, for the chips
 *
 * A12: a served note — reviewed and in date — answers with its rule, source, `as_of`, reviewer and
 * review date; a draft, a retired note, an expired review and another firm's note never answer; an
 * unmatched question answers `note: null` and nothing else of its own: no text is ever generated.
 * R15: every ask is logged.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, AssistantNoteSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->patricia = User::where('username', 'patricia')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function askAssistant(User $user, string $question, ?int $noteId = null): array
{
    $body = $noteId === null ? ['question' => $question] : ['question' => $question, 'note_id' => $noteId];

    return test()->actingAs($user)->postJson('/api/assistant/ask', $body)->assertOk()->json('data');
}

/** A note served by nobody's question but its own key, in the given state. */
function lonelyNote(Firm $firm, string $key, array $attributes = []): AssistantNote
{
    return AssistantNote::factory()->create([
        'firm_id' => $firm->id,
        'slug' => $key,
        'question' => "Pergunta {$key}?",
        'keywords_strong' => [$key],
        'keywords_weak' => [],
        ...$attributes,
    ]);
}

describe('a served note answers (A12)', function () {
    it('answers "quando começa a cobrança da CBS" with the cbs note, its rule, source, as_of, reviewer and date', function () {
        $answer = askAssistant($this->patricia, 'quando começa a cobrança da CBS');

        expect($answer['note']['slug'])->toBe('cbs')
            ->and($answer['note']['question'])->toBe('Quando a CBS começa a ser cobrada?')
            ->and($answer['note']['answer'])->toStartWith('Em 2026 é o ano de teste')
            ->and($answer['note']['rule'])->toBe('LC 214/2025, arts. 343 a 349 (transição)')
            ->and($answer['note']['source_label'])->not->toBeEmpty()
            ->and($answer['note']['source_url'])->toStartWith('https://')
            ->and($answer['note']['as_of'])->toBe('2026-11-05')
            ->and($answer['note']['reviewer']['name'])->toBe('Mariana Toledo')
            ->and($answer['note']['reviewed_at'])->toBe(CarbonImmutable::now()->subDays(2)->utc()->toIso8601String())
            ->and($answer['specialists'])->toBe([]);
    });

    it('lets Reinaldo ask too (assistant.ask: equipe and ceo)', function () {
        $reinaldo = User::where('username', 'reinaldo')->firstOrFail();

        expect(askAssistant($reinaldo, 'como funciona o split payment?')['note']['slug'])->toBe('split');
    });

    it('answers a chip by its note id, whatever the words', function () {
        $cashback = AssistantNote::withoutGlobalScopes()->where('slug', 'cashback')->firstOrFail();

        expect(askAssistant($this->patricia, 'Como funciona o cashback?', $cashback->id)['note']['slug'])->toBe('cashback');
    });

    it('goes through the ReformaAnswerer contract, never around it', function () {
        app()->instance(ReformaAnswerer::class, new class implements ReformaAnswerer
        {
            public function answer(string $question): ?AssistantNote
            {
                return null;
            }

            public function answerById(int $noteId): ?AssistantNote
            {
                return null;
            }
        });

        expect(askAssistant($this->patricia, 'quando começa a cobrança da CBS')['note'])->toBeNull();
    });
});

describe('nothing else ever answers (A12)', function () {
    it('never serves a draft: the seeded credito-simples (§2 unverified) nor a new one', function () {
        $draft = lonelyNote($this->franco, 'rascunhok', ['status' => 'rascunho', 'reviewer_id' => null, 'reviewed_at' => null]);
        $creditoSimples = AssistantNote::withoutGlobalScopes()->where('slug', 'credito-simples')->firstOrFail();

        expect(askAssistant($this->patricia, 'rascunhok')['note'])->toBeNull()
            ->and(askAssistant($this->patricia, 'rascunhok', $draft->id)['note'])->toBeNull()
            ->and(askAssistant($this->patricia, 'Comprar de fornecedor do Simples dá crédito?')['note']['slug'] ?? null)->not->toBe('credito-simples')
            ->and(askAssistant($this->patricia, 'Comprar de fornecedor do Simples dá crédito?', $creditoSimples->id)['note'])->toBeNull();
    });

    it('never serves a retired note', function () {
        $retired = lonelyNote($this->franco, 'aposentadak', ['status' => 'aposentada']);

        expect(askAssistant($this->patricia, 'aposentadak')['note'])->toBeNull()
            ->and(askAssistant($this->patricia, 'aposentadak', $retired->id)['note'])->toBeNull();
    });

    it('never serves a review older than 90 days', function () {
        $expired = lonelyNote($this->franco, 'vencidak', ['reviewed_at' => CarbonImmutable::now()->subDays(91)]);

        expect(askAssistant($this->patricia, 'vencidak')['note'])->toBeNull()
            ->and(askAssistant($this->patricia, 'vencidak', $expired->id)['note'])->toBeNull();
    });

    it("never serves firm B's note, by words or by id", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = lonelyNote($firmB, 'firmabk');

        expect(askAssistant($this->patricia, 'firmabk')['note'])->toBeNull()
            ->and(askAssistant($this->patricia, 'firmabk', $theirs->id)['note'])->toBeNull();
    });

    it("never lets firm B's better match hide Franco's own note", function () {
        // Firm B's split note scores higher (two weak keys more) and is newer: it must not even compete.
        lonelyNote(Firm::factory()->create(['slug' => 'firma-b']), 'split-deles', [
            'keywords_strong' => ['split', 'payment'],
            'keywords_weak' => ['funciona', 'como'],
            'reviewed_at' => CarbonImmutable::now(),
        ]);

        expect(askAssistant($this->patricia, 'como funciona o split payment?')['note']['slug'])->toBe('split');
    });

    it('answers an unmatched question with note null, the specialists to hand it to, and no text of its own', function () {
        $answer = askAssistant($this->patricia, 'qual o melhor ERP');

        expect(array_keys($answer))->toBe(['question', 'note', 'specialists'])
            ->and($answer['question'])->toBe('qual o melhor ERP')
            ->and($answer['note'])->toBeNull()
            ->and(array_column($answer['specialists'], 'name'))->toBe(['Carlos Prado', 'Juliana Reis', 'Mariana Toledo', 'Rafael Antunes']);
    });

    it('leaves the asking specialist out of the specialists to hand it to (review A2)', function () {
        $carlos = User::where('username', 'carlos.prado')->firstOrFail();

        expect(array_column(askAssistant($carlos, 'qual o melhor ERP')['specialists'], 'name'))->toBe(['Juliana Reis', 'Mariana Toledo', 'Rafael Antunes']);
    });

    it('offers only active specialists of the firm to hand an unmatched question to', function () {
        User::where('username', 'rafael.antunes')->update(['status' => 'inactive']);
        assistantPerson('equipe', specialist: true, firm: Firm::factory()->create(['slug' => 'firma-b']), attributes: ['name' => 'Bruno', 'last_name' => 'Firma B']);

        expect(array_column(askAssistant($this->patricia, 'qual o melhor ERP')['specialists'], 'name'))->toBe(['Carlos Prado', 'Juliana Reis', 'Mariana Toledo']);
    });
});

describe('the log (R15)', function () {
    it('logs every ask with who, the words, the normalised words and the note or none', function () {
        askAssistant($this->patricia, 'Quando começa a cobrança da CBS?');
        askAssistant($this->patricia, 'qual o melhor ERP');

        $asks = AssistantAsk::withoutGlobalScopes()->orderBy('id')->get();
        $cbs = AssistantNote::withoutGlobalScopes()->where('slug', 'cbs')->firstOrFail();

        expect($asks)->toHaveCount(2)
            ->and($asks->pluck('user_id')->unique()->all())->toBe([$this->patricia->id])
            ->and($asks->pluck('firm_id')->unique()->all())->toBe([$this->franco->id])
            ->and($asks[0]->question)->toBe('Quando começa a cobrança da CBS?')
            ->and($asks[0]->normalised)->toBe('quando comeca a cobranca da cbs')
            ->and($asks[0]->note_id)->toBe($cbs->id)
            ->and($asks[1]->note_id)->toBeNull()
            ->and($asks[1]->at->toIso8601String())->toBe(CarbonImmutable::now()->utc()->toIso8601String());
    });
});

describe('the question (R12, AM7)', function () {
    it('takes 3 to 300 characters', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/ask', ['question' => 'ab'])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->actingAs($this->patricia)->postJson('/api/assistant/ask', ['question' => str_repeat('a', 301)])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->actingAs($this->patricia)->postJson('/api/assistant/ask', ['question' => 'cbs'])->assertOk();
        $this->actingAs($this->patricia)->postJson('/api/assistant/ask', ['question' => str_repeat('a', 300)])->assertOk();

        expect(AssistantAsk::withoutGlobalScopes()->count())->toBe(2);
    });

    it('refuses a note id that is not a number', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/ask', ['question' => 'cbs', 'note_id' => 'cbs'])->assertUnprocessable()->assertJsonValidationErrors('note_id');
    });
});

describe('the chips (R12)', function () {
    it("lists the served notes' questions only: no draft, no retired, no expired, nothing of firm B", function () {
        lonelyNote($this->franco, 'aposentadak', ['status' => 'aposentada']);
        lonelyNote($this->franco, 'vencidak', ['reviewed_at' => CarbonImmutable::now()->subDays(91)]);
        lonelyNote(Firm::factory()->create(['slug' => 'firma-b']), 'firmabk');

        $chips = $this->actingAs($this->patricia)->getJson('/api/assistant/suggestions')->assertOk()->json('data');

        expect($chips)->toHaveCount(9)
            ->and($chips[0])->toHaveKeys(['id', 'question'])
            ->and(array_column($chips, 'question'))->toContain('Quando a CBS começa a ser cobrada?')
            ->not->toContain('Comprar de fornecedor do Simples dá crédito?')
            ->not->toContain('Pergunta aposentadak?')
            ->not->toContain('Pergunta vencidak?')
            ->not->toContain('Pergunta firmabk?');
    });
});
