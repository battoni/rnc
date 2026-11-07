<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Firm\Models\Firm;
use App\Modules\Notification\Notifications\AssistantQuestionNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;

/*
 * The hand-off (rnc-3 R13, W11): a question no served note answers goes to a specialist of the firm.
 *
 *   POST /api/assistant/questions   {question, specialist_id?} → one open question, one bell
 *
 * A13's hand-off half: one question and one bell for the specialist picked — the first active
 * specialist by name when none is — and 422 `assistant.notSpecialist` for anyone else (somebody
 * who is not a specialist, an inactive one, another firm's). The answer half is F5's.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->patricia = User::where('username', 'patricia')->firstOrFail();
    $this->carlos = User::where('username', 'carlos.prado')->firstOrFail();
    $this->mariana = User::where('username', 'mariana.toledo')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the hand-off (A13)', function () {
    it('creates one open question and rings only the picked specialist', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'Qual o melhor ERP?', 'specialist_id' => $this->mariana->id])
            ->assertCreated()
            ->assertJsonPath('data.question', 'Qual o melhor ERP?')
            ->assertJsonPath('data.status', 'aberta')
            ->assertJsonPath('data.specialist.name', 'Mariana Toledo');

        $question = AssistantQuestion::withoutGlobalScopes()->sole();
        $bells = DatabaseNotification::all();

        expect($question->firm_id)->toBe($this->franco->id)
            ->and($question->asked_by)->toBe($this->patricia->id)
            ->and($question->specialist_id)->toBe($this->mariana->id)
            ->and($question->answer)->toBeNull()
            ->and($question->answered_at)->toBeNull()
            ->and($bells)->toHaveCount(1)
            ->and($bells[0]->notifiable_id)->toBe($this->mariana->id)
            ->and($bells[0]->data)->toBe([
                'kind' => AssistantQuestionNotification::KIND,
                'question_id' => $question->id,
                'question' => 'Qual o melhor ERP?',
                'asked_by' => 'Patrícia Gomes',
            ]);
    });

    it('goes to the first active specialist by name when none is picked', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'Qual o melhor ERP?'])
            ->assertCreated()
            ->assertJsonPath('data.specialist.name', 'Carlos Prado');

        expect(DatabaseNotification::sole()->notifiable_id)->toBe($this->carlos->id);
    });

    it('refuses with 422 assistant.notSpecialist a target who is not an active specialist of the firm', function (Closure $target) {
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'Qual o melhor ERP?', 'specialist_id' => $target()->id])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'assistant.notSpecialist')
            ->assertJsonPath('code', 'not_specialist');

        expect(AssistantQuestion::withoutGlobalScopes()->count())->toBe(0)
            ->and(DatabaseNotification::count())->toBe(0);
    })->with([
        'Reinaldo, no specialist' => [fn () => User::where('username', 'reinaldo')->firstOrFail()],
        'Patrícia herself' => [fn () => User::where('username', 'patricia')->firstOrFail()],
        'an inactive specialist' => [fn () => tap(User::where('username', 'rafael.antunes')->firstOrFail())->update(['status' => 'inactive'])],
        "firm B's specialist" => [fn () => assistantPerson('equipe', specialist: true, firm: Firm::factory()->create(['slug' => 'firma-b']))],
    ]);

    it('refuses with 422 assistant.notSpecialist when the firm has no active specialist at all', function () {
        User::where('is_specialist', true)->update(['status' => 'inactive']);

        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'Qual o melhor ERP?'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'not_specialist');
    });

    it('takes a question of 3 to 300 characters', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'ab'])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => str_repeat('a', 301)])->assertUnprocessable()->assertJsonValidationErrors('question');
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => str_repeat('a', 300)])->assertCreated();
    });

    it('refuses a specialist id that is not a number', function () {
        $this->actingAs($this->patricia)->postJson('/api/assistant/questions', ['question' => 'Qual o melhor ERP?', 'specialist_id' => 'carlos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('specialist_id');
    });
});
