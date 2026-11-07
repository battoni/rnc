<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;

/*
 * The team's pipeline (rnc-1 F5, R18–R19, A11; plan AM8, AM13):
 *
 *   GET   /api/leads                 the board: one column per stage, its count and its cards (search)
 *   GET   /api/leads/{id}            the lead detail: profile, the FULL diagnostic, consent, meeting, timeline, notes
 *   PATCH /api/leads/{id}/stage      the team moves a lead to any other stage; perdido needs a reason
 *   POST  /api/leads/{id}/notes      a team note, on the timeline with who wrote it
 *
 * `leads.read` opens the reads and `leads.update` the writes. Another firm's lead is a 404.
 */
const PIPELINE_PROFILE = ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false];

function pipelineMember(string $role = 'equipe', string $username = 'patricia'): User
{
    return User::where('username', $username)->first() ?? User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function pipelineLead(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Padaria Exemplo Ltda',
        'contact_name' => 'Ana Exemplo',
        'phone' => '5511900000001',
        'email' => 'ana@exemplo.com.br',
        'profile' => PIPELINE_PROFILE,
    ], $overrides));
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->other = Firm::factory()->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the board (R18)', function () {
    it('answers one column per stage, in the pipeline order, with its count and cards', function () {
        pipelineLead(['stage' => 'lead_parcial', 'last_step' => 'perfil', 'company' => 'Mercado Parcial']);
        pipelineLead(['stage' => 'lead', 'company' => 'Oficina Lead']);
        pipelineLead(['stage' => 'perdido', 'company' => 'Loja Perdida', 'lost_reason' => 'Fechou com outro escritório']);
        Lead::factory()->create(['firm_id' => $this->other->id, 'stage' => 'lead', 'company' => 'De outra firma']);

        $columns = $this->actingAs(pipelineMember())->getJson('/api/leads')->assertOk()->json('data.columns');

        expect(array_column($columns, 'stage'))->toBe(['visitante', 'lead_parcial', 'lead', 'reuniao_marcada', 'proposta', 'cliente', 'perdido'])
            ->and(array_column($columns, 'count'))->toBe([0, 1, 1, 0, 0, 0, 1])
            ->and($columns[2]['items'][0])->toMatchArray([
                'company' => 'Oficina Lead',
                'stage' => 'lead',
                'sector' => 'servicos',
                'opportunities' => 3,
                'risks' => 2,
            ])
            ->and($columns[1]['items'][0]['last_step'])->toBe('perfil')
            ->and($columns[6]['items'][0]['lost_reason'])->toBe('Fechou com outro escritório');
    });

    it('shows the meeting on a booked card', function () {
        $lead = pipelineLead(['stage' => 'reuniao_marcada', 'company' => 'Clínica Agendada']);
        $specialist = Specialist::withoutGlobalScopes()->firstOrFail();
        Appointment::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id,
            'lead_id' => $lead->id,
            'specialist_id' => $specialist->id,
            'starts_at' => CarbonImmutable::parse('2026-11-24 14:30', 'America/Sao_Paulo'),
            'ends_at' => CarbonImmutable::parse('2026-11-24 15:00', 'America/Sao_Paulo'),
        ]);

        $columns = $this->actingAs(pipelineMember())->getJson('/api/leads')->assertOk()->json('data.columns');

        expect($columns[3]['items'][0]['meeting'])->toMatchArray([
            'starts_at' => '2026-11-24T14:30:00-03:00',
            'specialist' => $specialist->displayName(),
        ]);
    });

    it('searches by company, contact, e-mail or CNPJ', function () {
        pipelineLead(['company' => 'Padaria Sol']);
        pipelineLead(['company' => 'Oficina Lua', 'contact_name' => 'Bruno Sol']);
        pipelineLead(['company' => 'Mercado Estrela', 'contact_name' => 'Carla', 'email' => 'carla@estrela.com.br']);

        $columns = $this->actingAs(pipelineMember())->getJson('/api/leads?search=sol')->assertOk()->json('data.columns');

        expect(collect($columns)->flatMap(fn (array $column) => array_column($column['items'], 'company'))->sort()->values()->all())
            ->toBe(['Oficina Lua', 'Padaria Sol']);
    });

    it('reads % and _ in the search as plain characters, on every engine', function (string $search, array $found) {
        pipelineLead(['company' => 'Desconto 100% Ltda']);
        pipelineLead(['company' => 'Desconto 1000 Ltda']);
        pipelineLead(['company' => 'Loja A_B']);
        pipelineLead(['company' => 'Loja AXB']);
        pipelineLead(['company' => 'Bar Ponto!Final']);

        $columns = $this->actingAs(pipelineMember())->getJson('/api/leads?search='.rawurlencode($search))->assertOk()->json('data.columns');

        expect(collect($columns)->flatMap(fn (array $column) => array_column($column['items'], 'company'))->sort()->values()->all())
            ->toBe($found);
    })->with([
        'a percent sign' => ['100%', ['Desconto 100% Ltda']],
        'an underscore' => ['A_B', ['Loja A_B']],
        'the escape character' => ['o!F', ['Bar Ponto!Final']],
    ]);
});

describe('the lead detail (R19)', function () {
    it('shows the profile, the contact, the full diagnostic with details and the consent', function () {
        $lead = pipelineLead(['cnpj' => '11222333000181']);
        LeadConsent::query()->create(['lead_id' => $lead->id, 'text' => 'Autorizo a Franco…', 'version' => 1, 'given' => true, 'via' => 'formulario', 'given_at' => now()]);

        $detail = $this->actingAs(pipelineMember())->getJson("/api/leads/{$lead->id}")->assertOk()->json('data');

        expect($detail)->toMatchArray([
            'id' => $lead->id,
            'company' => 'Padaria Exemplo Ltda',
            'contact_name' => 'Ana Exemplo',
            'phone' => '5511900000001',
            'email' => 'ana@exemplo.com.br',
            'cnpj' => '11222333000181',
            'stage' => 'lead',
            'profile' => PIPELINE_PROFILE,
        ])
            ->and($detail['diagnosis']['opportunities'])->toBe(3)
            ->and($detail['diagnosis']['items'][0])->toHaveKeys(['kind', 'title', 'detail', 'rule', 'range_label'])
            ->and($detail['diagnosis']['items'][0]['detail'])->not->toBeEmpty()
            ->and($detail['consent'])->toMatchArray(['given' => true, 'text' => 'Autorizo a Franco…', 'version' => 1, 'via' => 'formulario']);
    });

    it('says when there is no consent answer and no diagnosis yet', function () {
        $lead = pipelineLead(['stage' => 'visitante', 'company' => null, 'contact_name' => null, 'phone' => null, 'email' => null, 'profile' => [...PIPELINE_PROFILE, 'band' => null]]);

        $detail = $this->actingAs(pipelineMember())->getJson("/api/leads/{$lead->id}")->assertOk()->json('data');

        expect($detail['consent'])->toBeNull()
            ->and($detail['diagnosis'])->toMatchArray(['opportunities' => 0, 'risks' => 0, 'items' => []]);
    });

    it('lists the timeline newest first, with who did each line', function () {
        $lead = pipelineLead();
        $patricia = pipelineMember();
        LeadEvent::query()->create(['lead_id' => $lead->id, 'type' => 'diagnostico.inicio', 'data' => ['path' => 'quiz'], 'at' => now()->subDay()]);
        LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $patricia->id, 'type' => 'nota.adicionada', 'data' => ['text' => 'Ligar na segunda'], 'at' => now()]);

        $detail = $this->actingAs($patricia)->getJson("/api/leads/{$lead->id}")->assertOk()->json('data');

        expect(array_column($detail['timeline'], 'type'))->toBe(['nota.adicionada', 'diagnostico.inicio'])
            ->and($detail['timeline'][0]['user'])->toBe(['id' => $patricia->id, 'name' => 'Patrícia Gomes'])
            ->and($detail['timeline'][1]['user'])->toBeNull()
            ->and($detail['notes'])->toHaveCount(1)
            ->and($detail['notes'][0]['data']['text'])->toBe('Ligar na segunda');
    });

    it('answers 404 for another firm\'s lead (A1)', function () {
        $foreign = Lead::factory()->create(['firm_id' => $this->other->id]);

        $this->actingAs(pipelineMember())->getJson("/api/leads/{$foreign->id}")->assertNotFound();
        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$foreign->id}/stage", ['stage' => 'cliente'])->assertNotFound();
        $this->actingAs(pipelineMember())->postJson("/api/leads/{$foreign->id}/notes", ['text' => 'oi'])->assertNotFound();

        expect(Lead::withoutGlobalScopes()->find($foreign->id)->stage)->toBe('lead_parcial');
    });
});

describe('moving a card (R18, A11)', function () {
    it('moves the lead and logs the move on its timeline with who and how', function () {
        $lead = pipelineLead();
        $patricia = pipelineMember();

        $answer = $this->actingAs($patricia)->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'proposta', 'via' => 'arrastar'])->assertOk()->json('data');

        expect($answer['lead'])->toMatchArray(['id' => $lead->id, 'stage' => 'proposta'])
            ->and($answer['event'])->toMatchArray(['type' => 'etapa.alterada', 'data' => ['from' => 'lead', 'to' => 'proposta', 'via' => 'arrastar']])
            ->and($answer['event']['user'])->toBe(['id' => $patricia->id, 'name' => 'Patrícia Gomes'])
            ->and($lead->fresh()->stage)->toBe('proposta');

        $event = LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'etapa.alterada')->sole();

        expect($event->user_id)->toBe($patricia->id)
            ->and($event->data)->toBe(['from' => 'lead', 'to' => 'proposta', 'via' => 'arrastar']);
    });

    it('stores the move to cliente for the funnel to count', function () {
        $lead = pipelineLead(['stage' => 'proposta']);

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente'])->assertOk();

        expect($lead->fresh()->stage)->toBe('cliente')
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'etapa.alterada')->sole()->data)
            ->toBe(['from' => 'proposta', 'to' => 'cliente', 'via' => 'botao']);
    });

    it('moves among all the stages, backwards too', function () {
        $lead = pipelineLead(['stage' => 'cliente']);

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'visitante', 'via' => 'ficha'])->assertOk();

        expect($lead->fresh()->stage)->toBe('visitante');
    });

    it('refuses perdido without a reason, and keeps the reason given', function () {
        $lead = pipelineLead();

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'perdido'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'pipeline.lostReasonRequired');
        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'perdido', 'reason' => '   '])->assertUnprocessable();

        expect($lead->fresh()->stage)->toBe('lead')
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->count())->toBe(0);

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'perdido', 'reason' => 'Sem orçamento este ano'])
            ->assertOk()
            ->assertJsonPath('data.lead.lost_reason', 'Sem orçamento este ano')
            ->assertJsonPath('data.event.data.reason', 'Sem orçamento este ano');
    });

    it('clears the reason when the lead comes back from perdido', function () {
        $lead = pipelineLead(['stage' => 'perdido', 'lost_reason' => 'Sem orçamento']);

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'lead'])->assertOk()->assertJsonPath('data.lead.lost_reason', null);

        expect($lead->fresh()->lost_reason)->toBeNull();
    });

    it('refuses the stage it is already in, and an unknown stage', function () {
        $lead = pipelineLead();

        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'lead'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.stage.0', 'pipeline.sameStage');
        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'ganho'])->assertUnprocessable()->assertJsonValidationErrors('stage');
        $this->actingAs(pipelineMember())->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente', 'via' => 'telepatia'])->assertUnprocessable()->assertJsonValidationErrors('via');
    });
});

describe('notes (R19)', function () {
    it('adds a note to the timeline with its author', function () {
        $lead = pipelineLead();
        $patricia = pipelineMember();

        $note = $this->actingAs($patricia)->postJson("/api/leads/{$lead->id}/notes", ['text' => '  Ligar na segunda, falar com o sócio.  '])->assertCreated()->json('data');

        expect($note)->toMatchArray(['type' => 'nota.adicionada', 'data' => ['text' => 'Ligar na segunda, falar com o sócio.']])
            ->and($note['user']['id'])->toBe($patricia->id)
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'nota.adicionada')->sole()->user_id)->toBe($patricia->id);
    });

    it('refuses an empty note', function () {
        $lead = pipelineLead();

        $this->actingAs(pipelineMember())->postJson("/api/leads/{$lead->id}/notes", ['text' => '  '])->assertUnprocessable()->assertJsonValidationErrors('text');
    });
});

describe('who may (AM8)', function () {
    it('needs a session', function () {
        $lead = pipelineLead();

        $this->getJson('/api/leads')->assertUnauthorized();
        $this->getJson("/api/leads/{$lead->id}")->assertUnauthorized();
        $this->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente'])->assertUnauthorized();
    });

    it('reads with leads.read and writes with leads.update', function () {
        $lead = pipelineLead();
        $nobody = User::factory()->create(['firm_id' => $this->franco->id]);

        $this->actingAs($nobody)->getJson('/api/leads')->assertForbidden();
        $this->actingAs($nobody)->getJson("/api/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($nobody)->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente'])->assertForbidden();
        $this->actingAs($nobody)->postJson("/api/leads/{$lead->id}/notes", ['text' => 'oi'])->assertForbidden();

        $this->actingAs(pipelineMember('ceo', 'reinaldo'))->patchJson("/api/leads/{$lead->id}/stage", ['stage' => 'cliente'])->assertOk();
    });
});
