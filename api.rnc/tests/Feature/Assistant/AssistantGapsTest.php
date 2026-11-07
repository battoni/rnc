<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantAsk;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Firm\Models\Firm;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/*
 * "Perguntas sem nota" (rnc-3 R15; A14's Pest half):
 *
 *   GET /api/assistant/gaps   the last 30 days' asks no note answered, one line per normalised text,
 *                             most asked first (`assistant.manage`)
 *
 * No notes are seeded here, so "Quando começa a CBS?" finds nothing — as it would before the base
 * had a CBS note.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->patricia = User::where('username', 'patricia')->firstOrFail();
    $this->reinaldo = User::where('username', 'reinaldo')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function askAs(User $user, string $question): void
{
    test()->actingAs($user)->postJson('/api/assistant/ask', ['question' => $question])->assertOk();
}

function gaps(User $user): array
{
    return test()->actingAs($user)->getJson('/api/assistant/gaps')->assertOk()->json('data');
}

describe('the gaps (A14)', function () {
    it('groups "Quando começa a CBS?" and "quando comeca a cbs" as one line asked twice', function () {
        askAs($this->patricia, 'Quando começa a CBS?');
        askAs($this->reinaldo, 'quando comeca a cbs');

        $lines = gaps($this->reinaldo);

        expect($lines)->toHaveCount(1)
            ->and($lines[0]['normalised'])->toBe('quando comeca a cbs')
            ->and($lines[0]['count'])->toBe(2)
            ->and($lines[0]['question'])->toBeIn(['Quando começa a CBS?', 'quando comeca a cbs'])
            ->and($lines[0]['last_asked_at'])->toBe(CarbonImmutable::now()->utc()->toIso8601String());
    });

    it('puts the most asked first', function () {
        askAs($this->patricia, 'qual o melhor ERP');
        askAs($this->patricia, 'Quando começa a CBS?');
        askAs($this->patricia, 'quando comeca a cbs');
        askAs($this->patricia, 'Quando começa a CBS');

        expect(array_column(gaps($this->reinaldo), 'count'))->toBe([3, 1])
            ->and(array_column(gaps($this->reinaldo), 'normalised'))->toBe(['quando comeca a cbs', 'qual o melhor erp']);
    });

    it('leaves out the asks older than 30 days', function () {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 09:00', 'America/Sao_Paulo'));
        askAs($this->patricia, 'pergunta antiga demais');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20 11:00', 'America/Sao_Paulo'));
        askAs($this->patricia, 'pergunta de 30 dias');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));

        expect(array_column(gaps($this->reinaldo), 'normalised'))->toBe(['pergunta de 30 dias']);
    });

    it('leaves out the asks a note answered', function () {
        AssistantNote::factory()->create(['firm_id' => $this->franco->id, 'slug' => 'split', 'keywords_strong' => ['split'], 'keywords_weak' => []]);

        askAs($this->patricia, 'como funciona o split payment?');
        askAs($this->patricia, 'qual o melhor ERP');

        expect(AssistantAsk::withoutGlobalScopes()->count())->toBe(2)
            ->and(array_column(gaps($this->reinaldo), 'normalised'))->toBe(['qual o melhor erp']);
    });

    it("leaves out firm B's asks", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        askAs(assistantPerson('equipe', firm: $firmB), 'pergunta da firma b');
        askAs($this->patricia, 'qual o melhor ERP');

        expect(array_column(gaps($this->reinaldo), 'normalised'))->toBe(['qual o melhor erp']);
    });

    it('answers in one query whatever the number of asks (plan P13)', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            gaps($this->reinaldo);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        askAs($this->patricia, 'qual o melhor ERP');
        $one = $count();

        foreach (range(1, 12) as $index) {
            askAs($this->patricia, "pergunta numero {$index}");
        }

        expect($count())->toBe($one);
    });

    it('refuses the team with 403 (assistant.manage)', function () {
        $this->actingAs($this->patricia)->getJson('/api/assistant/gaps')->assertForbidden();
    });
});
