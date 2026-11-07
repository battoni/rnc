<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Notification\Notifications\ReferralStatusNotification;
use App\Modules\Notification\Repositories\NotificationRepository;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * The partner's inbox and its moves (rnc-2 F2, R4–R9; A3, A5, A6, A8's Pest half; plan AM1):
 *
 *   GET  /api/portal/referrals                   the signed-in partner's referrals, open first, newest first
 *   GET  /api/portal/referrals/{id}              one of them
 *   POST /api/portal/referrals/{id}/transition   {to, meeting_at?, deal_value_cents?} — one step of the graph
 *
 * Every route is `permission:portal.access` + `portal`, and the repository filters by
 * `CurrentPartner`: another partner's referral, another firm's, or an id that is a lead's is a 404,
 * whatever `X-Firm` the request sends. The lead's contact appears only once the partner accepts;
 * a portfolio (`carteira`) lead's way in is Franco's own phone and mailbox. No answer ever carries
 * a range, a note or the lead's timeline. Now: Monday 16/11/2026 10:00 in São Paulo. A whole number
 * of hours reaches the wire as an integer (`3`, not `3.0`): JSON keeps no zero fraction.
 */
const INBOX_PROFILE = ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false];

function inboxFrancoId(): int
{
    return (int) Firm::where('slug', 'franco')->value('id');
}

function inboxPartner(int $firmId, string $name, string $specialty = 'erp_nfe'): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => $firmId,
        'name' => $name,
        'specialty' => $specialty,
        'email' => str($name)->slug().'@parceiro.exemplo',
        'active' => true,
    ]);
}

function inboxLogin(Partner $partner): User
{
    return User::factory()->create([
        'firm_id' => $partner->firm_id,
        'role_id' => Role::where('slug', 'parceiro')->value('id'),
        'partner_id' => $partner->id,
        'name' => $partner->name,
        'last_name' => '',
    ]);
}

function inboxStaff(string $role): User
{
    return User::factory()->create(['firm_id' => inboxFrancoId(), 'role_id' => Role::where('slug', $role)->value('id')]);
}

/** @param  array<string, mixed>  $lead  @param  array<string, mixed>  $referral */
function inboxReferral(Partner $partner, array $lead = [], array $referral = []): Referral
{
    $row = Lead::factory()->create([
        'firm_id' => $partner->firm_id,
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Consultoria Prisma Ltda',
        'contact_name' => 'Ana Exemplo',
        'phone' => '5511900000077',
        'email' => 'ana.prisma@exemplo.com.br',
        'profile' => INBOX_PROFILE,
        ...$lead,
    ]);
    $referrer = User::query()->where('firm_id', $partner->firm_id)->value('id') ?? User::factory()->create(['firm_id' => $partner->firm_id])->id;
    $at = CarbonImmutable::parse('2026-11-13 16:00', 'America/Sao_Paulo');

    return Referral::withoutGlobalScopes()->forceCreate([
        'firm_id' => $partner->firm_id,
        'lead_id' => $row->id,
        'partner_id' => $partner->id,
        'referred_by' => $referrer,
        'message' => 'A Franco indica a Consultoria Prisma Ltda. Podem fazer o primeiro contato em até 24 horas úteis?',
        'score' => 81,
        'covers' => [['id' => 'nfe_layout', 'title' => 'Novo layout da NF-e'], ['id' => 'erp_ready', 'title' => 'ERP pronto para 2027']],
        'consent_text' => 'Autorizo a Franco a me apresentar a parceiros.',
        'consent_version' => 1,
        'consent_given_at' => $at->subDay(),
        'status' => 'enviado',
        'created_at' => $at,
        'updated_at' => $at,
        ...$referral,
    ]);
}

/** @param  array<string, mixed>  $extra */
function inboxMove(User $login, Referral $referral, string $to, array $extra = []): TestResponse
{
    return test()->actingAs($login)->postJson("/api/portal/referrals/{$referral->id}/transition", ['to' => $to, ...$extra]);
}

/** Walks a referral along the graph, as its partner, until it stands at `$status`. */
function inboxBringTo(User $login, Referral $referral, string $status): void
{
    $steps = [
        'aceito' => [],
        'contato' => [],
        'reuniao' => ['meeting_at' => '2026-11-19T14:00:00-03:00'],
        'proposta' => [],
        'fechado' => ['deal_value_cents' => 1_200_000],
    ];

    if ($status === 'enviado') {
        return;
    }

    foreach ($steps as $to => $extra) {
        inboxMove($login, $referral, $to, $extra)->assertOk();

        if ($to === $status) {
            return;
        }
    }
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->other = Firm::factory()->create();
    $this->nexo = inboxPartner(inboxFrancoId(), 'NexoERP (exemplo)');
    $this->giro = inboxPartner(inboxFrancoId(), 'Giro Capital (exemplo)', 'credito_capital_giro');
    $this->foreign = inboxPartner($this->other->id, 'Parceiro de outra firma');
    $this->nexoLogin = inboxLogin($this->nexo);
    $this->giroLogin = inboxLogin($this->giro);
    $this->foreignLogin = inboxLogin($this->foreign);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the inbox (R5)', function () {
    it('lists the partner\'s referrals, open before closed, newest first', function () {
        $old = inboxReferral($this->nexo, ['company' => 'Antiga Ltda'], ['created_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
        $closed = inboxReferral($this->nexo, ['company' => 'Fechada Ltda'], ['status' => 'fechado', 'created_at' => CarbonImmutable::parse('2026-11-14 10:00'), 'contacted_at' => CarbonImmutable::parse('2026-11-03 10:00'), 'closed_at' => CarbonImmutable::parse('2026-11-12 10:00'), 'deal_value_cents' => 1_200_000]);
        $declined = inboxReferral($this->nexo, ['company' => 'Recusada Ltda'], ['status' => 'recusado', 'created_at' => CarbonImmutable::parse('2026-11-15 10:00')]);
        $new = inboxReferral($this->nexo, ['company' => 'Nova Ltda']);

        $ids = collect($this->actingAs($this->nexoLogin)->getJson('/api/portal/referrals')->assertOk()->json('data'))->pluck('id')->all();

        expect($ids)->toBe([$new->id, $old->id, $declined->id, $closed->id]);
    });

    it('answers the profile, the covered titles, the score, the message, the dates and the SLA', function () {
        $referral = inboxReferral($this->nexo);

        $answer = $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$referral->id}")->assertOk()->json('data');

        expect($answer)->toBe([
            'id' => $referral->id,
            'status' => 'enviado',
            'received_at' => '2026-11-13T19:00:00+00:00',
            'status_at' => null,
            'company' => 'Consultoria Prisma Ltda',
            'profile' => ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2'],
            'covers' => ['Novo layout da NF-e', 'ERP pronto para 2027'],
            'score' => 81,
            'message' => 'A Franco indica a Consultoria Prisma Ltda. Podem fazer o primeiro contato em até 24 horas úteis?',
            'sla' => ['state' => 'ok', 'hours' => 3],
            'contacted_at' => null,
            'meeting_at' => null,
            'closed_at' => null,
            'contact' => null,
        ]);
    });
});

describe('isolation (R4, A3, AM1)', function () {
    it('never lists another partner\'s referral, nor another firm\'s (A3)', function () {
        $mine = inboxReferral($this->nexo);
        inboxReferral($this->giro);
        inboxReferral($this->foreign);

        $this->actingAs($this->nexoLogin)->getJson('/api/portal/referrals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });

    it('answers 404 to reading or moving another partner\'s referral, and leaves it as it was (A3)', function () {
        $theirs = inboxReferral($this->giro);

        $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$theirs->id}")->assertNotFound();
        inboxMove($this->nexoLogin, $theirs, 'aceito')->assertNotFound();
        inboxMove($this->nexoLogin, $theirs, 'recusado')->assertNotFound();

        expect($theirs->fresh()->status)->toBe('enviado')
            ->and(LeadEvent::query()->where('type', 'encaminhamento.status')->count())->toBe(0);
    });

    it('answers 404 to another firm\'s referral, even sending that firm\'s X-Firm (A3, AM1)', function () {
        $foreign = inboxReferral($this->foreign);

        $this->actingAs($this->nexoLogin)->withHeader('X-Firm', $this->other->slug)
            ->getJson("/api/portal/referrals/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->nexoLogin)->withHeader('X-Firm', $this->other->slug)
            ->postJson("/api/portal/referrals/{$foreign->id}/transition", ['to' => 'aceito'])->assertNotFound();
        $this->actingAs($this->nexoLogin)->withHeader('X-Firm', $this->other->slug)
            ->getJson('/api/portal/referrals')->assertOk()->assertJsonCount(0, 'data');

        expect(Referral::withoutGlobalScopes()->find($foreign->id)->status)->toBe('enviado');
    });

    it('keeps another firm\'s partner out of Franco\'s referrals, whatever its X-Firm says (A3, AM1)', function () {
        $franco = inboxReferral($this->nexo);

        $this->actingAs($this->foreignLogin)->withHeader('X-Firm', 'franco')
            ->getJson('/api/portal/referrals')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->foreignLogin)->withHeader('X-Firm', 'franco')
            ->getJson("/api/portal/referrals/{$franco->id}")->assertNotFound();
    });

    it('reaches no lead by its own id: the id is the referral\'s, and only the partner\'s own (A3)', function () {
        $mine = inboxReferral($this->nexo);
        $neverReferred = Lead::factory()->create(['firm_id' => inboxFrancoId()]);
        $unknown = max($mine->id, $neverReferred->id) + 100;

        $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$neverReferred->id}")->assertNotFound();
        $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$unknown}")->assertNotFound();
        $this->actingAs($this->nexoLogin)->postJson("/api/portal/referrals/{$neverReferred->id}/transition", ['to' => 'aceito'])->assertNotFound();
    });

    it('refuses the operator and staff', function (string $method, string $uri) {
        $referral = inboxReferral($this->nexo);
        $uri = str_replace('{id}', (string) $referral->id, $uri);

        $this->actingAs(inboxStaff('operador'))->json($method, $uri, ['to' => 'aceito'])->assertForbidden()->assertJsonPath('error', 'portal.noPartner');
        $this->actingAs(inboxStaff('equipe'))->json($method, $uri, ['to' => 'aceito'])->assertForbidden();
        $this->actingAs(inboxStaff('ceo'))->json($method, $uri, ['to' => 'aceito'])->assertForbidden();

        expect($referral->fresh()->status)->toBe('enviado');
    })->with([
        'list' => ['GET', '/api/portal/referrals'],
        'show' => ['GET', '/api/portal/referrals/{id}'],
        'move' => ['POST', '/api/portal/referrals/{id}/transition'],
    ]);
    it('asks a guest to sign in', function (string $method, string $uri) {
        $referral = inboxReferral($this->nexo);

        $this->json($method, str_replace('{id}', (string) $referral->id, $uri), ['to' => 'aceito'])->assertUnauthorized();

        expect($referral->fresh()->status)->toBe('enviado');
    })->with([
        'list' => ['GET', '/api/portal/referrals'],
        'show' => ['GET', '/api/portal/referrals/{id}'],
        'move' => ['POST', '/api/portal/referrals/{id}/transition'],
    ]);
});

describe('what the partner sees (R6, A5, D3)', function () {
    it('carries no contact and no R$ field before the partner accepts (A5)', function () {
        $referral = inboxReferral($this->nexo);

        $show = $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$referral->id}")->assertOk();
        $list = $this->actingAs($this->nexoLogin)->getJson('/api/portal/referrals')->assertOk();

        foreach ([$show->getContent(), $list->getContent()] as $body) {
            expect($body)->not->toContain('Ana Exemplo')
                ->not->toContain('5511900000077')
                ->not->toContain('ana.prisma@exemplo.com.br')
                ->not->toContain('_cents')
                ->not->toContain('range');
        }

        expect($show->json('data.contact'))->toBeNull();
    });

    it('never shows a declined referral\'s contact (A5, D3)', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'recusado')->assertOk()->assertJsonPath('data.contact', null);
    });

    it('shows the lead\'s contact from the accept on (A5)', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'aceito')->assertOk()
            ->assertJsonPath('data.contact', ['via' => 'lead', 'name' => 'Ana Exemplo', 'phone' => '5511900000077', 'email' => 'ana.prisma@exemplo.com.br']);
        $this->actingAs($this->nexoLogin)->getJson('/api/portal/referrals')
            ->assertJsonPath('data.0.contact.name', 'Ana Exemplo');
    });

    it('shows Franco\'s phone and mailbox as the way in to a portfolio client (A5, R27)', function () {
        $referral = inboxReferral($this->nexo, ['channel' => 'carteira', 'contact_name' => null, 'phone' => null, 'email' => null], ['status' => 'aceito']);
        $firm = Firm::find(inboxFrancoId());

        $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$referral->id}")->assertOk()
            ->assertJsonPath('data.contact', ['via' => 'firm', 'name' => $firm->name, 'phone' => '551137325500', 'email' => $firm->mailbox]);
    });

    it('answers the firm\'s phone with the country code however it is stored (A5)', function () {
        $referral = inboxReferral($this->nexo, ['channel' => 'carteira', 'contact_name' => null, 'phone' => null, 'email' => null], ['status' => 'aceito']);

        foreach (['11 3732-5500', '(11) 3732-5500', '551137325500', '+55 11 3732-5500'] as $stored) {
            Firm::find(inboxFrancoId())->update(['phone' => $stored]);

            $this->actingAs($this->nexoLogin)->getJson("/api/portal/referrals/{$referral->id}")
                ->assertJsonPath('data.contact.phone', '551137325500');
        }
    });

    it('never answers a range, a note, the timeline or another referral (A5)', function () {
        $referral = inboxReferral($this->nexo);
        $lead = Lead::withoutGlobalScopes()->find($referral->lead_id);
        $lead->update(['profile' => [...INBOX_PROFILE, 'range' => [1000, 5000], 'notes' => 'nota interna']]);
        LeadEvent::query()->create(['lead_id' => $lead->id, 'type' => 'nota.adicionada', 'data' => ['text' => 'Ligar só à tarde'], 'at' => now()]);
        inboxReferral($this->giro, ['company' => 'Outra Ltda']);
        inboxMove($this->nexoLogin, $referral, 'aceito')->assertOk();

        foreach (['/api/portal/referrals', "/api/portal/referrals/{$referral->id}"] as $uri) {
            $body = $this->actingAs($this->nexoLogin)->getJson($uri)->assertOk()->getContent();

            expect($body)->not->toContain('range')
                ->not->toContain('notes')
                ->not->toContain('nota interna')
                ->not->toContain('Ligar só à tarde')
                ->not->toContain('Outra Ltda')
                ->not->toContain('events')
                ->not->toContain('consent');
        }
    });

    it('shows the partner its own contract value once closed', function () {
        $referral = inboxReferral($this->nexo);

        inboxBringTo($this->nexoLogin, $referral, 'proposta');
        inboxMove($this->nexoLogin, $referral, 'fechado', ['deal_value_cents' => 2_400_000])->assertOk()
            ->assertJsonPath('data.deal_value_cents', 2_400_000);
    });
});

describe('the moves (R7, A6)', function () {
    it('walks enviado → aceito → contato → reunião → proposta → fechado, storing who and when (A6)', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'aceito')->assertOk()->assertJsonPath('data.status', 'aceito');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 11:30', 'America/Sao_Paulo'));
        inboxMove($this->nexoLogin, $referral, 'contato')->assertOk()
            ->assertJsonPath('data.contacted_at', '2026-11-16T14:30:00+00:00')
            ->assertJsonPath('data.sla', ['state' => 'cumprido', 'hours' => 4.5]);
        inboxMove($this->nexoLogin, $referral, 'reuniao', ['meeting_at' => '2026-11-19T14:00:00-03:00'])->assertOk()
            ->assertJsonPath('data.meeting_at', '2026-11-19T17:00:00+00:00');
        inboxMove($this->nexoLogin, $referral, 'proposta')->assertOk();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-23 09:00', 'America/Sao_Paulo'));
        inboxMove($this->nexoLogin, $referral, 'fechado', ['deal_value_cents' => 1_200_000])->assertOk()
            ->assertJsonPath('data.status', 'fechado')
            ->assertJsonPath('data.closed_at', '2026-11-23T12:00:00+00:00')
            ->assertJsonPath('data.status_at', '2026-11-23T12:00:00+00:00');

        $row = $referral->fresh();

        expect($row->status)->toBe('fechado')
            ->and($row->status_by)->toBe($this->nexoLogin->id)
            ->and($row->deal_value_cents)->toBe(1_200_000)
            ->and($row->contacted_at->toIso8601String())->toBe('2026-11-16T14:30:00+00:00')
            ->and($row->meeting_at->toIso8601String())->toBe('2026-11-19T17:00:00+00:00')
            ->and($row->closed_at->toIso8601String())->toBe('2026-11-23T12:00:00+00:00');
    });

    it('lets a new referral be declined', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'recusado')->assertOk()
            ->assertJsonPath('data.status', 'recusado')
            ->assertJsonPath('data.sla', ['state' => 'encerrado', 'hours' => 0]);
    });

    it('refuses every move off the graph with referral.invalidTransition (A6)', function (string $from, string $to) {
        $referral = inboxReferral($this->nexo);
        inboxBringTo($this->nexoLogin, $referral, $from);
        $extra = ['meeting_at' => '2026-11-27T10:00:00-03:00', 'deal_value_cents' => 500_000];

        inboxMove($this->nexoLogin, $referral, $to, $extra)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'referral.invalidTransition')
            ->assertJsonPath('code', 'invalid_transition');

        expect($referral->fresh()->status)->toBe($from);
    })->with([
        'enviado → contato (skips the accept)' => ['enviado', 'contato'],
        'enviado → fechado' => ['enviado', 'fechado'],
        'enviado → enviado' => ['enviado', 'enviado'],
        'aceito → recusado (accepted is accepted)' => ['aceito', 'recusado'],
        'aceito → reuniao' => ['aceito', 'reuniao'],
        'contato → aceito (no going back)' => ['contato', 'aceito'],
        'contato → proposta' => ['contato', 'proposta'],
        'reuniao → fechado' => ['reuniao', 'fechado'],
        'proposta → contato' => ['proposta', 'contato'],
        'fechado → proposta (terminal)' => ['fechado', 'proposta'],
    ]);

    it('lets a declined referral move nowhere (A6)', function (string $to) {
        $referral = inboxReferral($this->nexo, referral: ['status' => 'recusado']);

        inboxMove($this->nexoLogin, $referral, $to, ['meeting_at' => '2026-11-27T10:00:00-03:00', 'deal_value_cents' => 500_000])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_transition');
    })->with(['enviado', 'aceito', 'contato', 'reuniao', 'proposta', 'fechado', 'recusado']);

    it('asks a reunião for a meeting date-time in the future (A6)', function (array $extra, string $key) {
        $referral = inboxReferral($this->nexo);
        inboxBringTo($this->nexoLogin, $referral, 'contato');

        inboxMove($this->nexoLogin, $referral, 'reuniao', $extra)->assertUnprocessable()->assertJsonPath('errors.meeting_at.0', $key);

        expect($referral->fresh()->status)->toBe('contato');
    })->with([
        'missing' => [[], 'portal.referrals.meetingRequired'],
        'in the past' => [['meeting_at' => '2026-11-16T09:00:00-03:00'], 'portal.referrals.meetingInPast'],
        'not a date' => [['meeting_at' => 'amanhã'], 'portal.referrals.meetingInvalid'],
    ]);

    it('asks a fechado for a first-year contract value above zero (A6)', function (array $extra, string $key) {
        $referral = inboxReferral($this->nexo);
        inboxBringTo($this->nexoLogin, $referral, 'proposta');

        inboxMove($this->nexoLogin, $referral, 'fechado', $extra)->assertUnprocessable()->assertJsonPath('errors.deal_value_cents.0', $key);

        expect($referral->fresh()->status)->toBe('proposta');
    })->with([
        'missing' => [[], 'portal.referrals.dealValueRequired'],
        'zero' => [['deal_value_cents' => 0], 'portal.referrals.dealValueInvalid'],
        'negative' => [['deal_value_cents' => -100], 'portal.referrals.dealValueInvalid'],
        'not whole cents' => [['deal_value_cents' => 12.5], 'portal.referrals.dealValueInvalid'],
    ]);

    it('refuses a status that does not exist', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'ganhou')->assertUnprocessable()->assertJsonValidationErrors('to');
    });

    it('keeps no meeting or value sent with a move that does not take one', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'aceito', ['meeting_at' => '2026-11-27T10:00:00-03:00', 'deal_value_cents' => 900])->assertOk();

        expect($referral->fresh()->meeting_at)->toBeNull()->and($referral->fresh()->deal_value_cents)->toBeNull();
    });
});

describe('Franco hears the partner (R9, A8 Pest half)', function () {
    it('writes one encaminhamento.status line per move, by the partner, and never moves the lead\'s stage (A8)', function () {
        $referral = inboxReferral($this->nexo);
        $lead = Lead::withoutGlobalScopes()->find($referral->lead_id);

        inboxBringTo($this->nexoLogin, $referral, 'fechado');

        $lines = LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'encaminhamento.status')->orderBy('id')->get();

        expect($lines)->toHaveCount(5)
            ->and($lines->pluck('data.to')->all())->toBe(['aceito', 'contato', 'reuniao', 'proposta', 'fechado'])
            ->and($lines->pluck('data.from')->all())->toBe(['enviado', 'aceito', 'contato', 'reuniao', 'proposta'])
            ->and($lines->every(fn (LeadEvent $line) => $line->user_id === $this->nexoLogin->id))->toBeTrue()
            ->and($lines->every(fn (LeadEvent $line) => $line->data['referral_id'] === $referral->id && $line->data['partner'] === 'NexoERP (exemplo)'))->toBeTrue()
            ->and($lines[2]->data['meeting_at'])->toBe('2026-11-19T17:00:00+00:00')
            ->and($lines[4]->data['deal_value_cents'])->toBe(1_200_000)
            ->and($lead->fresh()->stage)->toBe('lead');
    });

    it('rings every active equipe and ceo member on aceito, recusado and fechado — and only then (A8)', function () {
        $team = app(NotificationRepository::class)->findTeamMembersOfFirm(inboxFrancoId())->pluck('id')->sort()->values()->all();
        $accepted = inboxReferral($this->nexo, ['company' => 'Aceita Ltda']);
        $declined = inboxReferral($this->nexo, ['company' => 'Recusada Ltda']);

        inboxBringTo($this->nexoLogin, $accepted, 'fechado');
        inboxMove($this->nexoLogin, $declined, 'recusado')->assertOk();

        $rings = DatabaseNotification::query()->where('type', ReferralStatusNotification::class)->get();
        $byStatus = $rings->groupBy('data.status');

        expect($team)->not->toBeEmpty()
            ->and($rings)->toHaveCount(3 * count($team))
            ->and($byStatus->keys()->sort()->values()->all())->toBe(['aceito', 'fechado', 'recusado'])
            ->and($byStatus['aceito']->pluck('notifiable_id')->sort()->values()->all())->toBe($team)
            ->and($byStatus['fechado']->first()->data)->toBe([
                'kind' => 'referral_status',
                'lead_id' => $accepted->lead_id,
                'referral_id' => $accepted->id,
                'company' => 'Aceita Ltda',
                'partner' => 'NexoERP (exemplo)',
                'status' => 'fechado',
                'deal_value_cents' => 1_200_000,
            ])
            ->and($byStatus['recusado']->first()->data['company'])->toBe('Recusada Ltda');
    });

    it('shows Franco\'s routing panel and trail the partner\'s status, SLA and, once closed, the contract value (A8, R10)', function () {
        $referral = inboxReferral($this->nexo);
        $equipe = inboxStaff('equipe');
        $ceo = inboxStaff('ceo');
        $panel = fn () => collect($this->actingAs($equipe)->getJson("/api/leads/{$referral->lead_id}/partner-matches")->assertOk()->json('data.matches'))
            ->firstWhere('partner.id', $this->nexo->id)['referral'];
        $trail = fn () => collect($this->actingAs($ceo)->getJson('/api/referrals')->assertOk()->json('data.data'))->firstWhere('id', $referral->id);

        expect($panel())->toMatchArray(['status' => 'enviado', 'sla' => ['state' => 'ok', 'hours' => 3], 'deal_value_cents' => null])
            ->and($trail())->toMatchArray(['status' => 'enviado', 'sla' => ['state' => 'ok', 'hours' => 3], 'deal_value_cents' => null]);

        inboxBringTo($this->nexoLogin, $referral, 'fechado');

        expect($panel())->toMatchArray(['status' => 'fechado', 'sla' => ['state' => 'cumprido', 'hours' => 3], 'deal_value_cents' => 1_200_000])
            ->and($panel()['status_at'])->toBe('2026-11-16T13:00:00+00:00')
            ->and($trail())->toMatchArray(['status' => 'fechado', 'sla' => ['state' => 'cumprido', 'hours' => 3], 'deal_value_cents' => 1_200_000, 'status_at' => '2026-11-16T13:00:00+00:00']);
    });

    it('writes nothing when the move is refused', function () {
        $referral = inboxReferral($this->nexo);

        inboxMove($this->nexoLogin, $referral, 'fechado', ['deal_value_cents' => 500])->assertUnprocessable();

        expect(LeadEvent::query()->where('type', 'encaminhamento.status')->count())->toBe(0)
            ->and(DB::table('notifications')->count())->toBe(0);
    });
});
