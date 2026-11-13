<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Partner\Repositories\ReferralRepository;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Routing and the trail (rnc-1 F6, R22–R23, A12; plan AM6, AM8):
 *
 *   GET  /api/leads/{id}/partner-matches   every active partner scored against the lead's diagnostic
 *   POST /api/leads/{id}/referrals         Franco recommends the lead to one partner — only with consent
 *   GET  /api/referrals?partner_id=        the trail, newest first (`referrals.read`: ceo, operador)
 *
 * The score (plan Defaults): 70 for one covered item + 11 per extra, capped at 97; a partner that
 * covers nothing gets 12–28, fixed per partner and lead. The lead stays Franco's: its stage does not
 * move, the partner's e-mail carries the company and Franco's contact, never the lead's.
 */
const ROUTING_PROFILE = ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false];

/** The demo's eight placeholders, one per specialty (facts.md §3). */
const ROUTING_PARTNERS = [
    ['Andrade Tributário (exemplo)', 'advocacia_tributaria'],
    ['Recupera Créditos (exemplo)', 'recuperacao_creditos'],
    ['NexoERP (exemplo)', 'erp_nfe'],
    ['Giro Capital (exemplo)', 'credito_capital_giro'],
    ['Amparo Seguros (exemplo)', 'seguros_beneficios'],
    ['Herança Societário (exemplo)', 'societario_sucessao'],
    ['Margem Certa (exemplo)', 'precificacao_controladoria'],
    ['CertiDigital (exemplo)', 'certificado_digital'],
];

function routingPerson(string $role): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
        'name' => ucfirst($role),
        'last_name' => 'Franco',
    ]);
}

/** @return array<string, Partner> by specialty */
function routingPartners(): array
{
    $firmId = Firm::where('slug', 'franco')->value('id');
    $partners = [];

    foreach (ROUTING_PARTNERS as $index => [$name, $specialty]) {
        $partners[$specialty] = Partner::withoutGlobalScopes()->create([
            'firm_id' => $firmId,
            'name' => $name,
            'specialty' => $specialty,
            'email' => 'contato'.($index + 1).'@parceiro.exemplo',
            'active' => true,
        ]);
    }

    return $partners;
}

function routingLead(?bool $consent, array $overrides = []): Lead
{
    $lead = Lead::factory()->create(array_merge([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Consultoria Prisma Ltda',
        'contact_name' => 'Ana Exemplo',
        'phone' => '5511900000077',
        'email' => 'ana.prisma@exemplo.com.br',
        'profile' => ROUTING_PROFILE,
    ], $overrides));

    if ($consent !== null) {
        LeadConsent::query()->create(['lead_id' => $lead->id, 'text' => ConsentTerms::TEXT, 'version' => ConsentTerms::VERSION, 'given' => $consent, 'via' => ConsentTerms::VIA_FORM, 'given_at' => CarbonImmutable::parse('2026-11-18 09:30', 'America/Sao_Paulo')]);
    }

    return $lead;
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    Mail::fake();
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->other = Firm::factory()->create();
    $this->partners = routingPartners();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the match scores (R22)', function () {
    it('scores each active partner by the diagnostic items its specialty covers, best first', function () {
        $lead = routingLead(true);
        $this->partners['certificado_digital']->update(['active' => false]);

        $answer = $this->actingAs(routingPerson('equipe'))->getJson("/api/leads/{$lead->id}/partner-matches")->assertOk()->json('data');
        $matches = collect($answer['matches'])->keyBy('partner.specialty');

        expect($answer['can_refer'])->toBeTrue()
            ->and($answer['blocked_reason'])->toBeNull()
            ->and($matches)->toHaveCount(7)
            ->and($matches->has('certificado_digital'))->toBeFalse()
            ->and($answer['matches'][0]['partner']['specialty'])->toBe('precificacao_controladoria')
            ->and($matches['precificacao_controladoria']['score'])->toBe(92)
            ->and(array_column($matches['precificacao_controladoria']['covers'], 'id'))->toBe(['simples-credito', 'servicos-30', 'contratos'])
            ->and($matches['advocacia_tributaria']['score'])->toBe(81)
            ->and($matches['credito_capital_giro']['score'])->toBe(81)
            ->and($matches['societario_sucessao']['score'])->toBe(70)
            ->and($matches['societario_sucessao']['covers'][0]['title'])->toBeString()->not->toBeEmpty();

        foreach (['recuperacao_creditos', 'erp_nfe', 'seguros_beneficios'] as $uncovered) {
            expect($matches[$uncovered]['covers'])->toBe([])
                ->and($matches[$uncovered]['score'])->toBeGreaterThanOrEqual(12)->toBeLessThanOrEqual(28);
        }
    });

    it('keeps an uncovered score fixed for the same partner and lead', function () {
        $lead = routingLead(true);
        $member = routingPerson('equipe');

        $first = collect($this->actingAs($member)->getJson("/api/leads/{$lead->id}/partner-matches")->json('data.matches'))->pluck('score', 'partner.id');
        $again = collect($this->actingAs($member)->getJson("/api/leads/{$lead->id}/partner-matches")->json('data.matches'))->pluck('score', 'partner.id');

        expect($again->all())->toBe($first->all());
    });

    it('caps the score at 97', function () {
        $lead = routingLead(true, ['profile' => ['sector' => 'agro', 'regime' => 'presumido', 'band' => 'b3', 'b2b' => 70, 'incentivos' => true, 'exporta' => true]]);

        $scores = array_column($this->actingAs(routingPerson('equipe'))->getJson("/api/leads/{$lead->id}/partner-matches")->json('data.matches'), 'score');

        expect(max($scores))->toBeLessThanOrEqual(97);
    });

    it('says why a lead without consent cannot be referred', function (?bool $consent, string $reason) {
        $lead = routingLead($consent);

        $this->actingAs(routingPerson('equipe'))->getJson("/api/leads/{$lead->id}/partner-matches")
            ->assertOk()
            ->assertJsonPath('data.can_refer', false)
            ->assertJsonPath('data.blocked_reason', $reason);
    })->with([
        'never answered' => [null, 'consent_missing'],
        'answered no' => [false, 'consent_refused'],
    ]);

    it('answers 404 for another firm\'s lead (A1)', function () {
        $foreign = Lead::factory()->create(['firm_id' => $this->other->id, 'profile' => ROUTING_PROFILE]);

        $this->actingAs(routingPerson('equipe'))->getJson("/api/leads/{$foreign->id}/partner-matches")->assertNotFound();
    });
});

describe('a referral (R22, A12)', function () {
    it('is refused without consent (422 consent_required), and nothing is written or sent', function (?bool $consent) {
        $lead = routingLead($consent);

        $this->actingAs(routingPerson('equipe'))->postJson("/api/leads/{$lead->id}/referrals", [
            'partner_id' => $this->partners['erp_nfe']->id,
            'message' => 'A Franco indica a Consultoria Prisma.',
        ])->assertUnprocessable()->assertJsonPath('code', 'consent_required');

        expect(Referral::withoutGlobalScopes()->count())->toBe(0)
            ->and(LeadEvent::where('type', 'encaminhamento.enviado')->count())->toBe(0);
        Mail::assertNothingQueued();
    })->with(['never answered' => [null], 'answered no' => [false]]);

    it('with consent, records the referral with the consent snapshot, a timeline line and the stage untouched', function () {
        $lead = routingLead(true, ['stage' => 'reuniao_marcada']);
        $member = routingPerson('equipe');
        $partner = $this->partners['precificacao_controladoria'];

        $answer = $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", [
            'partner_id' => $partner->id,
            'message' => '  A Franco indica a Consultoria Prisma. Podem fazer o primeiro contato em até 24 horas úteis?  ',
        ])->assertCreated()->json('data');

        $referral = Referral::withoutGlobalScopes()->sole();

        expect($referral)->toMatchArray([
            'lead_id' => $lead->id,
            'partner_id' => $partner->id,
            'referred_by' => $member->id,
            'status' => 'enviado',
            'score' => 92,
            'consent_text' => ConsentTerms::TEXT,
            'consent_version' => ConsentTerms::VERSION,
            'message' => 'A Franco indica a Consultoria Prisma. Podem fazer o primeiro contato em até 24 horas úteis?',
        ])
            ->and($referral->consent_given_at->toIso8601String())->toBe('2026-11-18T09:30:00-03:00')
            ->and($lead->fresh()->stage)->toBe('reuniao_marcada')
            ->and($answer['referral'])->toMatchArray(['status' => 'enviado', 'score' => 92])
            ->and($answer['referral']['partner']['name'])->toBe('Margem Certa (exemplo)')
            ->and($answer['event'])->toMatchArray(['type' => 'encaminhamento.enviado'])
            ->and($answer['event']['data'])->toMatchArray(['partner_id' => $partner->id, 'partner' => 'Margem Certa (exemplo)'])
            ->and($answer['event']['user']['name'])->toBe('Equipe Franco');

        $line = LeadEvent::where('lead_id', $lead->id)->where('type', 'encaminhamento.enviado')->sole();
        expect($line->user_id)->toBe($member->id);
    });

    it('queues the branded ReferralSent to the partner with the company and Franco\'s contact, never the lead\'s', function () {
        $lead = routingLead(true);

        $this->actingAs(routingPerson('equipe'))->postJson("/api/leads/{$lead->id}/referrals", [
            'partner_id' => $this->partners['erp_nfe']->id,
            'message' => 'A Franco indica a Consultoria Prisma.',
        ])->assertCreated();

        Mail::assertQueued(ReferralSent::class, function (ReferralSent $mail) {
            $html = $mail->render();
            $text = view('mail.referral-sent-text', ['referral' => $mail->referral])->render();

            foreach ([$html, $text] as $body) {
                expect($body)->toContain('Consultoria Prisma Ltda')
                    ->toContain('A Franco indica a Consultoria Prisma.')
                    ->toContain('Equipe Franco')
                    ->not->toContain('900000077')
                    ->not->toContain('ana.prisma@exemplo.com.br')
                    ->not->toContain('Ana Exemplo');
            }

            return $mail->hasTo('contato3@parceiro.exemplo') && $mail->locale === 'pt_BR';
        });
        Mail::assertQueuedCount(1);
    });

    it('refuses the same partner twice for one lead (409 already_referred)', function () {
        $lead = routingLead(true);
        $member = routingPerson('equipe');
        $payload = ['partner_id' => $this->partners['erp_nfe']->id, 'message' => 'Indicação.'];

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", $payload)->assertCreated();
        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", $payload)->assertConflict()->assertJsonPath('code', 'already_referred');

        expect(Referral::withoutGlobalScopes()->count())->toBe(1);
    });

    /* Two clicks in flight: the pre-check misses the other's row, the unique (lead, partner) index refuses. */
    it('answers 409 already_referred when the insert meets the unique index the pre-check missed', function (bool $postgres) {
        $lead = routingLead(true);
        $member = routingPerson('equipe');
        $payload = ['partner_id' => $this->partners['erp_nfe']->id, 'message' => 'Indicação.'];

        // Bound before the first request: the router keeps the controller (and its service) it built.
        $this->app->instance(ReferralRepository::class, new class($postgres) extends ReferralRepository
        {
            public function __construct(private bool $postgres) {}

            public function existsReferralOfLeadToPartner(Lead $lead, int $partnerId): bool
            {
                return false;
            }

            public function createReferral(array $attributes): Referral
            {
                $taken = Referral::query()->where('lead_id', $attributes['lead_id'])->where('partner_id', $attributes['partner_id'])->exists();

                return $this->postgres && $taken ? throw postgresUniqueViolation('referrals') : parent::createReferral($attributes);
            }
        });

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", $payload)->assertCreated();

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", $payload)->assertConflict()->assertJsonPath('code', 'already_referred');

        expect(Referral::withoutGlobalScopes()->count())->toBe(1)
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'encaminhamento.enviado')->count())->toBe(1);
    })->with([
        'the real index (SQLite says 23000)' => [false],
        'PostgreSQL says 23505' => [true],
    ]);

    it('refuses an inactive partner, another firm\'s partner and an empty message', function () {
        $lead = routingLead(true);
        $member = routingPerson('equipe');
        $inactive = $this->partners['seguros_beneficios'];
        $inactive->update(['active' => false]);
        $foreign = Partner::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'name' => 'De outra firma', 'specialty' => 'erp_nfe', 'email' => 'x@outra.exemplo', 'active' => true]);

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", ['partner_id' => $inactive->id, 'message' => 'Oi.'])->assertJsonValidationErrors(['partner_id']);
        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", ['partner_id' => $foreign->id, 'message' => 'Oi.'])->assertJsonValidationErrors(['partner_id']);
        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", ['partner_id' => $this->partners['erp_nfe']->id, 'message' => '   '])->assertJsonValidationErrors(['message']);

        expect(Referral::withoutGlobalScopes()->count())->toBe(0);
    });

    it('marks the partner already referred on the matches', function () {
        $lead = routingLead(true);
        $member = routingPerson('equipe');

        $this->actingAs($member)->postJson("/api/leads/{$lead->id}/referrals", ['partner_id' => $this->partners['erp_nfe']->id, 'message' => 'Indicação.'])->assertCreated();

        $matches = collect($this->actingAs($member)->getJson("/api/leads/{$lead->id}/partner-matches")->json('data.matches'))->keyBy('partner.specialty');

        expect($matches['erp_nfe']['referral'])->toMatchArray(['status' => 'enviado', 'at' => '2026-11-19T10:00:00-03:00'])
            ->and($matches['advocacia_tributaria']['referral'])->toBeNull();
    });

    it('needs leads.update to refer', function () {
        $lead = routingLead(true);
        $reader = userWithPermissions(['leads.read']);
        $reader->update(['firm_id' => Firm::where('slug', 'franco')->value('id')]);

        $this->actingAs($reader)->postJson("/api/leads/{$lead->id}/referrals", ['partner_id' => $this->partners['erp_nfe']->id, 'message' => 'Oi.'])->assertForbidden();
    });
});

describe('the trail (R23, A2)', function () {
    beforeEach(function () {
        $this->member = routingPerson('equipe');
        $this->first = routingLead(true, ['company' => 'Primeira Ltda']);
        $this->second = routingLead(true, ['company' => 'Segunda Ltda']);

        $this->actingAs($this->member)->postJson("/api/leads/{$this->first->id}/referrals", ['partner_id' => $this->partners['erp_nfe']->id, 'message' => 'Primeira.'])->assertCreated();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 11:00', 'America/Sao_Paulo'));
        $this->actingAs($this->member)->postJson("/api/leads/{$this->second->id}/referrals", ['partner_id' => $this->partners['advocacia_tributaria']->id, 'message' => 'Segunda.'])->assertCreated();
    });

    it('shows every referral newest first, with the consent given, who and when', function () {
        $items = $this->actingAs(routingPerson('ceo'))->getJson('/api/referrals')->assertOk()->json('data.data');

        expect(array_column(array_column($items, 'lead'), 'company'))->toBe(['Segunda Ltda', 'Primeira Ltda'])
            ->and($items[0])->toMatchArray(['status' => 'enviado', 'at' => '2026-11-19T11:00:00-03:00', 'message' => 'Segunda.'])
            ->and($items[0]['partner'])->toMatchArray(['name' => 'Andrade Tributário (exemplo)', 'specialty' => 'advocacia_tributaria'])
            ->and($items[0]['referred_by']['name'])->toBe('Equipe Franco')
            ->and($items[0]['consent'])->toMatchArray(['given' => true, 'text' => ConsentTerms::TEXT, 'version' => ConsentTerms::VERSION, 'at' => '2026-11-18T09:30:00-03:00'])
            ->and($items[0])->not->toHaveKey('phone');
    });

    it('filters by partner', function () {
        $items = $this->actingAs(routingPerson('operador'))->getJson('/api/referrals?partner_id='.$this->partners['erp_nfe']->id)->assertOk()->json('data.data');

        expect($items)->toHaveCount(1)
            ->and($items[0]['lead']['company'])->toBe('Primeira Ltda');
    });

    it('is closed to the team (403)', function () {
        $this->actingAs($this->member)->getJson('/api/referrals')->assertForbidden();
    });

    it('never shows another firm\'s referral', function () {
        $foreignLead = Lead::factory()->create(['firm_id' => $this->other->id, 'profile' => ROUTING_PROFILE]);
        $foreignPartner = Partner::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'name' => 'De outra firma', 'specialty' => 'erp_nfe', 'email' => 'x@outra.exemplo', 'active' => true]);
        Referral::withoutGlobalScopes()->create(['firm_id' => $this->other->id, 'lead_id' => $foreignLead->id, 'partner_id' => $foreignPartner->id, 'referred_by' => $this->member->id, 'message' => 'x', 'score' => 70, 'covers' => [], 'consent_text' => 'x', 'consent_version' => 1, 'consent_given_at' => now(), 'status' => 'enviado']);

        expect($this->actingAs(routingPerson('ceo'))->getJson('/api/referrals')->json('data.total'))->toBe(2);
    });
});
