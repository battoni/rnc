<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;

/*
 * A37 (rnc-4 R32; plan F5a): the firm's manager (`firm.settings`) edits each specialist's weekly
 * hours, and the visitor's availability follows; a person newly flagged a specialist starts on the
 * firm's business hours. Another firm's specialist is 404, the team is 403.
 *
 *   GET /api/specialists                 the firm's specialists with their hours
 *   GET /api/specialists/{id}/hours      one specialist's hours
 *   PUT /api/specialists/{id}/hours      replaces them
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo (as PublicBookingTest): the second offered day
 * is Monday 23/11.
 */
const HOURS_ZONE = 'America/Sao_Paulo';

const HOURS_PROFILE = ['sector' => 'saude', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 30, 'incentivos' => false, 'exporta' => false];

function hoursSpecialist(string $username): Specialist
{
    return Specialist::withoutGlobalScopes()->whereHas('user', fn ($query) => $query->where('username', $username))->sole();
}

/** @return list<string> the specialist's offered slots on Monday 23/11 */
function mondaySlotsOf(Specialist $specialist): array
{
    $lead = Lead::factory()->create([
        'firm_id' => $specialist->firm_id,
        'stage' => 'lead',
        'last_step' => 'resultado',
        'profile' => HOURS_PROFILE,
    ]);

    $availability = test()->withHeader('X-Firm', 'franco')->getJson("/api/public/diagnostics/{$lead->token}/availability")->assertOk()->json('data');
    $days = collect($availability['specialists'])->firstWhere('id', $specialist->id)['days'];

    return collect($days)->firstWhere('date', '2026-11-23')['slots'] ?? [];
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', HOURS_ZONE));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->alvorada = Firm::factory()->create(['slug' => 'alvorada']);
    $this->manager = assistantPerson('gestao');
    $this->team = assistantPerson('equipe');
    $this->mariana = hoursSpecialist('mariana.toledo');

    $alvoradaPerson = assistantPerson('equipe', specialist: true, firm: $this->alvorada);
    $this->foreign = Specialist::withoutGlobalScopes()->create(['firm_id' => $this->alvorada->id, 'user_id' => $alvoradaPerson->id, 'area' => 'Fiscal', 'focus' => 'geral']);
    $this->foreign->hours()->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '17:00']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('reading the hours', function () {
    it("lists the firm's specialists with their hours, never another firm's", function () {
        $list = $this->actingAs($this->manager)->getJson('/api/specialists')->assertOk()->json('data');
        $ids = array_column($list, 'id');

        expect($ids)->toContain($this->mariana->id)
            ->not->toContain($this->foreign->id)
            ->and(collect($list)->firstWhere('id', $this->mariana->id)['name'])->toBe('Mariana Toledo')
            ->and(collect($list)->firstWhere('id', $this->mariana->id)['hours'][0])->toBe(['weekday' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00']);
    });

    it("answers one specialist's hours, and 404 for another firm's", function () {
        $this->actingAs($this->manager)->getJson("/api/specialists/{$this->mariana->id}/hours")
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->actingAs($this->manager)->getJson("/api/specialists/{$this->foreign->id}/hours")->assertNotFound();
    });

    it('refuses the team', function () {
        $this->actingAs($this->team)->getJson('/api/specialists')->assertForbidden();
        $this->actingAs($this->team)->getJson("/api/specialists/{$this->mariana->id}/hours")->assertForbidden();
    });
});

describe('PUT /api/specialists/{id}/hours (A37)', function () {
    it("changes what the visitor's availability offers", function () {
        expect(mondaySlotsOf($this->mariana))->toHaveCount(20);

        $this->actingAs($this->manager)->putJson("/api/specialists/{$this->mariana->id}/hours", [
            'hours' => [
                ['weekday' => 1, 'opens_at' => '14:00', 'closes_at' => '16:00'],
                ['weekday' => 3, 'opens_at' => '09:00', 'closes_at' => '12:00'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0', ['weekday' => 1, 'opens_at' => '14:00', 'closes_at' => '16:00']);

        $this->app['auth']->forgetGuards();

        expect(mondaySlotsOf($this->mariana->fresh()))->toBe([
            '2026-11-23T14:00:00-03:00',
            '2026-11-23T14:30:00-03:00',
            '2026-11-23T15:00:00-03:00',
            '2026-11-23T15:30:00-03:00',
        ]);
    });

    it('empties the week when sent no hours', function () {
        $this->actingAs($this->manager)->putJson("/api/specialists/{$this->mariana->id}/hours", ['hours' => []])->assertOk()->assertJsonCount(0, 'data');

        expect($this->mariana->hours()->count())->toBe(0);
    });

    it("gets a 404 on another firm's specialist, and its hours stay", function () {
        $this->actingAs($this->manager)->putJson("/api/specialists/{$this->foreign->id}/hours", ['hours' => []])->assertNotFound();

        expect($this->foreign->hours()->count())->toBe(1);
    });

    it('refuses the team, and writes nothing', function () {
        $this->actingAs($this->team)->putJson("/api/specialists/{$this->mariana->id}/hours", ['hours' => []])->assertForbidden();

        expect($this->mariana->hours()->count())->toBe(5);
    });

    it('refuses hours that do not read as a week', function (?array $hours) {
        $this->actingAs($this->manager)->putJson("/api/specialists/{$this->mariana->id}/hours", ['hours' => $hours])->assertUnprocessable();

        expect($this->mariana->hours()->count())->toBe(5);
    })->with([
        'a weekday out of range' => [[['weekday' => 8, 'opens_at' => '09:00', 'closes_at' => '12:00']]],
        'closing before opening' => [[['weekday' => 1, 'opens_at' => '12:00', 'closes_at' => '09:00']]],
        'a time that is not HH:MM' => [[['weekday' => 1, 'opens_at' => '9', 'closes_at' => '12:00']]],
        'no list' => [null],
    ]);
});

describe('a newly flagged specialist (R32)', function () {
    it("starts on the firm's business hours when created flagged", function () {
        $this->actingAs($this->manager)->postJson('/api/auth/users', [
            'name' => 'Nova',
            'last_name' => 'Especialista',
            'username' => 'nova.especialista',
            'role_id' => Role::where('slug', 'equipe')->value('id'),
            'password' => 'segredo-forte-1',
            'status' => 'active',
            'is_specialist' => true,
        ])->assertCreated();

        $specialist = hoursSpecialist('nova.especialista');

        expect($specialist->hours()->orderBy('weekday')->get(['weekday', 'opens_at', 'closes_at'])->toArray())->toBe([
            ['weekday' => 1, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['weekday' => 2, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['weekday' => 3, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['weekday' => 4, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['weekday' => 5, 'opens_at' => '08:00', 'closes_at' => '17:00'],
        ]);
    });

    it("starts on the firm's business hours when an existing member is flagged", function () {
        $member = assistantPerson('equipe', attributes: ['username' => 'membro.franco']);

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$member->id}", ['is_specialist' => true])->assertOk();

        expect(hoursSpecialist('membro.franco')->hours()->count())->toBe(5);
    });

    it('keeps the hours a specialist already has when the flag is sent again', function () {
        $this->mariana->hours()->delete();
        $this->mariana->hours()->create(['weekday' => 2, 'opens_at' => '10:00', 'closes_at' => '11:00']);
        $person = User::find($this->mariana->user_id);

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$person->id}", ['is_specialist' => true])->assertOk();

        expect($this->mariana->hours()->get(['weekday', 'opens_at', 'closes_at'])->toArray())->toBe([['weekday' => 2, 'opens_at' => '10:00', 'closes_at' => '11:00']]);
    });
});

describe('a specialist switched off (R31)', function () {
    it('leaves the agenda and the hours list, and keeps its row and hours for the day it is switched back on', function () {
        $person = User::find($this->mariana->user_id);

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$person->id}", ['is_specialist' => false])->assertOk();

        expect(array_column($this->actingAs($this->manager)->getJson('/api/specialists')->assertOk()->json('data'), 'id'))->not->toContain($this->mariana->id);

        $lead = Lead::factory()->create(['firm_id' => $this->franco->id, 'stage' => 'lead', 'last_step' => 'resultado', 'profile' => HOURS_PROFILE]);
        $offered = array_column($this->getJson("/api/public/diagnostics/{$lead->token}/availability")->assertOk()->json('data.specialists'), 'id');

        expect($offered)->not->toContain($this->mariana->id)
            ->and($this->mariana->hours()->count())->toBe(5);

        $this->actingAs($this->manager)->patchJson("/api/auth/users/{$person->id}", ['is_specialist' => true])->assertOk();

        expect(array_column($this->actingAs($this->manager)->getJson('/api/specialists')->json('data'), 'id'))->toContain($this->mariana->id);
    });
});
