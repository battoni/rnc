<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Enums\LeadEventType;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

/*
 * A48 (rnc-4 R39, F9a): the lead's own data, for an LGPD request.
 *
 *   GET  /api/leads/{id}/export      the lead's fields, its consents (with the words read) and its timeline, as JSON
 *   POST /api/leads/{id}/anonymise   name, e-mail, phone, CNPJ and company wiped; the row, its stage, its consents
 *                                    and its timeline kept (the funnel still counts it); a timeline line says who and when
 *
 * Behind `leads.update` AND the manager's `firm.settings`: the team (`equipe`) holds the first and
 * not the second, so it is refused. Another firm's lead is a 404, never a 403. The negative cases
 * come first, on purpose.
 */
const PRIVACY_PROFILE = ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false];

function privacyPerson(string $role, Firm $firm): User
{
    return User::factory()->create([
        'firm_id' => $firm->id,
        'role_id' => Role::where('slug', $role)->value('id'),
    ]);
}

function privacyLead(Firm $firm, array $overrides = []): Lead
{
    $lead = Lead::factory()->create(array_merge([
        'firm_id' => $firm->id,
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Padaria Exemplo Ltda',
        'cnpj' => '12345678000195',
        'contact_name' => 'Ana Exemplo',
        'phone' => '5511900000001',
        'email' => 'ana@exemplo.com.br',
        'profile' => PRIVACY_PROFILE,
        'report_token' => Str::random(48),
        'report_expires_at' => now()->addDays(30),
    ], $overrides));

    LeadConsent::query()->create(['lead_id' => $lead->id, 'text' => 'Autorizo o escritório a me contatar.', 'version' => 2, 'given' => true, 'via' => 'formulario', 'given_at' => now()->subDay()]);
    LeadEvent::query()->create(['lead_id' => $lead->id, 'type' => 'contato.capturado', 'data' => ['consent' => true], 'at' => now()->subDay()]);

    return $lead;
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
    $this->other = Firm::factory()->create(['slug' => 'alvorada']);
    $this->manager = privacyPerson('gestao', $this->franco);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('who may export or anonymise (A48, negatives first)', function () {
    it("answers 404 for another firm's lead, and leaves it untouched", function (string $method, string $suffix) {
        $foreign = privacyLead($this->other, ['email' => 'segredo@outra.exemplo']);

        $this->actingAs($this->manager)->json($method, "/api/leads/{$foreign->id}/{$suffix}")->assertNotFound();

        expect($foreign->fresh()->email)->toBe('segredo@outra.exemplo')
            ->and($foreign->fresh()->anonymised_at)->toBeNull();
    })->with([['GET', 'export'], ['POST', 'anonymise']]);

    it('refuses the team (leads.update without the manager key) with 403, and writes nothing', function (string $method, string $suffix) {
        $lead = privacyLead($this->franco);

        $this->actingAs(privacyPerson('equipe', $this->franco))->json($method, "/api/leads/{$lead->id}/{$suffix}")->assertForbidden();

        expect($lead->fresh()->email)->toBe('ana@exemplo.com.br')
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->count())->toBe(1);
    })->with([['GET', 'export'], ['POST', 'anonymise']]);

    it('refuses an account holding the manager key without leads.update with 403', function (string $method, string $suffix) {
        $lead = privacyLead($this->franco);
        $holder = userWithPermissions(['firm.settings']);
        $holder->forceFill(['firm_id' => $this->franco->id])->save();

        $this->actingAs($holder)->json($method, "/api/leads/{$lead->id}/{$suffix}")->assertForbidden();
    })->with([['GET', 'export'], ['POST', 'anonymise']]);

    it('asks a guest to sign in', function (string $method, string $suffix) {
        $lead = privacyLead($this->franco);

        $this->json($method, "/api/leads/{$lead->id}/{$suffix}")->assertUnauthorized();
    })->with([['GET', 'export'], ['POST', 'anonymise']]);

    it("refuses the manager sending another firm's header with 403", function () {
        $lead = privacyLead($this->franco);

        $this->actingAs($this->manager)->withHeader('X-Firm', 'alvorada')->postJson("/api/leads/{$lead->id}/anonymise")->assertForbidden();

        expect($lead->fresh()->anonymised_at)->toBeNull();
    });
});

describe('the export (A48)', function () {
    it("answers the lead's fields, its consents with the words read and its timeline", function () {
        $lead = privacyLead($this->franco);

        $data = $this->actingAs($this->manager)->getJson("/api/leads/{$lead->id}/export")->assertOk()->json('data');

        expect($data['lead'])->toMatchArray([
            'id' => $lead->id,
            'company' => 'Padaria Exemplo Ltda',
            'cnpj' => '12345678000195',
            'contact_name' => 'Ana Exemplo',
            'phone' => '5511900000001',
            'email' => 'ana@exemplo.com.br',
            'stage' => 'lead',
            'profile' => PRIVACY_PROFILE,
            'anonymised_at' => null,
        ])
            ->and($data['consents'])->toHaveCount(1)
            ->and($data['consents'][0])->toMatchArray(['version' => 2, 'text' => 'Autorizo o escritório a me contatar.', 'given' => true, 'via' => 'formulario'])
            ->and(array_column($data['timeline'], 'type'))->toBe(['contato.capturado'])
            ->and($data['controller'])->toBe(['name' => 'Franco Contabilidade'])
            ->and($data['exported_at'])->not->toBeNull();
    });

    it("never includes another firm's lead, consents or timeline, nor its tokens", function () {
        $lead = privacyLead($this->franco, ['report_token' => str_repeat('r', 48)]);
        $foreign = privacyLead($this->other, ['email' => 'segredo@outra.exemplo', 'contact_name' => 'Pessoa De Fora', 'cnpj' => '99999999000199']);
        LeadConsent::query()->create(['lead_id' => $foreign->id, 'text' => 'Texto da outra firma', 'version' => 9, 'given' => true, 'via' => 'formulario', 'given_at' => now()]);

        $body = $this->actingAs($this->manager)->getJson("/api/leads/{$lead->id}/export")->assertOk()->getContent();

        expect($body)->not->toContain('segredo@outra.exemplo')
            ->and($body)->not->toContain('Pessoa De Fora')
            ->and($body)->not->toContain('99999999000199')
            ->and($body)->not->toContain('Texto da outra firma')
            ->and($body)->not->toContain($lead->token)
            ->and($body)->not->toContain(str_repeat('r', 48));
    });

    it("leaves the team's authors out of the timeline (another person's data)", function () {
        $lead = privacyLead($this->franco);
        $author = privacyPerson('equipe', $this->franco);
        $author->forceFill(['name' => 'Autora', 'last_name' => 'Daequipe'])->save();
        LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $author->id, 'type' => 'nota.adicionada', 'data' => ['text' => 'Ligar na segunda'], 'at' => now()]);

        $data = $this->actingAs($this->manager)->getJson("/api/leads/{$lead->id}/export")->assertOk()->json('data');

        expect($data['timeline'][1])->toBe(['type' => 'nota.adicionada', 'data' => ['text' => 'Ligar na segunda'], 'at' => now()->toIso8601String()])
            ->and(json_encode($data))->not->toContain('Daequipe');
    });
});

describe('the anonymisation (A48)', function () {
    it('wipes name, e-mail, phone, CNPJ and company, and keeps the row, its stage, consents and timeline', function () {
        $lead = privacyLead($this->franco);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk()
            ->assertJsonPath('data.id', $lead->id)
            ->assertJsonPath('data.contact_name', null)
            ->assertJsonPath('data.anonymised_at', now()->toIso8601String());

        $row = Lead::withoutGlobalScopes()->find($lead->id);
        expect($row)->not->toBeNull()
            ->and($row->only(['company', 'cnpj', 'contact_name', 'phone', 'email', 'visitor_uuid', 'report_token']))->toBe([
                'company' => null, 'cnpj' => null, 'contact_name' => null, 'phone' => null, 'email' => null, 'visitor_uuid' => null, 'report_token' => null,
            ])
            ->and($row->stage)->toBe('lead')
            ->and($row->profile)->toBeIgnoringKeyOrder(PRIVACY_PROFILE)
            ->and($row->anonymised_at?->toIso8601String())->toBe(now()->toIso8601String())
            ->and(LeadConsent::query()->where('lead_id', $lead->id)->sole()->text)->toBe('Autorizo o escritório a me contatar.');
    });

    it('writes a timeline line with who did it and when', function () {
        $lead = privacyLead($this->franco);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();

        $line = LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'dados.anonimizados')->sole();
        expect($line->user_id)->toBe($this->manager->id)
            ->and($line->at->toIso8601String())->toBe(now()->toIso8601String())
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'contato.capturado')->exists())->toBeTrue();
    });

    it('wipes the free text the team wrote about the lead (notes, a lost reason), keeping the lines', function () {
        $lead = privacyLead($this->franco, ['stage' => 'perdido', 'lost_reason' => 'Ana disse que o sócio João não quer']);
        LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $this->manager->id, 'type' => 'nota.adicionada', 'data' => ['text' => 'Ana Exemplo, celular pessoal 11 9999-0000'], 'at' => now()->subHour()]);
        LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $this->manager->id, 'type' => 'etapa.alterada', 'data' => ['from' => 'lead', 'to' => 'perdido', 'via' => 'ficha', 'reason' => 'Ana disse que o sócio João não quer'], 'at' => now()->subHour()]);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();

        $events = LeadEvent::query()->where('lead_id', $lead->id)->get()->keyBy('type');
        expect($lead->fresh()->lost_reason)->toBeNull()
            ->and($lead->fresh()->stage)->toBe('perdido')
            ->and($events['nota.adicionada']->data)->toBe(['text' => null])
            ->and($events['etapa.alterada']->data)->toBeIgnoringKeyOrder(['from' => 'lead', 'to' => 'perdido', 'via' => 'ficha'])
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->get()->toJson())->not->toContain('Ana');
    });

    it('decides every timeline type: one line of each, none keeps the name, e-mail or phone, and the export carries no token (verify P2-A6)', function () {
        $lead = privacyLead($this->franco, ['report_token' => str_repeat('r', 48)]);
        $samples = [
            LeadEventType::DiagnosticStarted->value => ['path' => 'quiz'],
            LeadEventType::CnpjLookedUp->value => ['found' => true],
            LeadEventType::ContactCaptured->value => ['consent' => true],
            LeadEventType::StepReached->value => ['step' => 'resultado'],
            LeadEventType::ResultSeen->value => ['opportunities' => 2, 'risks' => 1],
            LeadEventType::MeetingBooked->value => ['specialist' => 'Mariana Toledo', 'starts_at' => '2026-11-20T10:00:00-03:00'],
            LeadEventType::StageChanged->value => ['from' => 'lead', 'to' => 'perdido', 'via' => 'ficha', 'reason' => 'Ana Exemplo, 5511900000001'],
            LeadEventType::NoteAdded->value => ['text' => 'Falar com Ana Exemplo em ana@exemplo.com.br'],
            LeadEventType::ReferralSent->value => ['referral_id' => 1, 'partner_id' => 1, 'partner' => 'Parceiro Um', 'specialty' => 'tributario', 'score' => 80],
            LeadEventType::ReferralStatusChanged->value => ['referral_id' => 1, 'partner_id' => 1, 'partner' => 'Parceiro Um', 'from' => 'enviado', 'to' => 'reuniao'],
            LeadEventType::Anonymised->value => ['via' => 'pedido'],
        ];
        expect(array_keys($samples))->toEqualCanonicalizing(array_column(LeadEventType::cases(), 'value'));

        foreach ($samples as $type => $data) {
            LeadEvent::query()->create(['lead_id' => $lead->id, 'user_id' => $this->manager->id, 'type' => $type, 'data' => $data, 'at' => now()->subHour()]);
        }

        $export = $this->actingAs($this->manager)->getJson("/api/leads/{$lead->id}/export")->assertOk()->getContent();
        expect($export)->not->toContain($lead->token)->not->toContain($lead->report_token);

        $this->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();

        $timeline = LeadEvent::query()->where('lead_id', $lead->id)->get()->toJson();
        expect($timeline)->not->toContain('Ana')->not->toContain('ana@exemplo.com.br')->not->toContain('5511900000001');
        foreach (LeadEventType::cases() as $type) {
            expect($type->withoutPersonalData($samples[$type->value]))->toBeArray();
        }
    });

    it('is irreversible: the old diagnostic and report links no longer reach the lead', function () {
        $lead = privacyLead($this->franco, ['report_token' => str_repeat('r', 48)]);
        $oldToken = $lead->token;

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();
        $this->app['auth']->forgetGuards();

        expect(Lead::withoutGlobalScopes()->where('token', $oldToken)->exists())->toBeFalse()
            ->and($lead->fresh()->token_expires_at->lessThanOrEqualTo(now()))->toBeTrue();
        $this->getJson("/api/public/diagnostics/{$oldToken}/result")->assertNotFound();
        $this->getJson('/api/public/reports/'.str_repeat('r', 48))->assertNotFound();
    });

    it('is idempotent: a second call answers the same lead and writes no second line', function () {
        $lead = privacyLead($this->franco);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();
        CarbonImmutable::setTestNow(now()->addHour());
        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk()
            ->assertJsonPath('data.anonymised_at', now()->subHour()->toIso8601String());

        expect(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'dados.anonimizados')->count())->toBe(1);
    });

    it("keeps the lead in the board's count", function () {
        $lead = privacyLead($this->franco);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();

        $columns = $this->actingAs($this->manager)->getJson('/api/leads')->assertOk()->json('data.columns');
        expect(collect($columns)->firstWhere('stage', 'lead')['count'])->toBe(1);
    });

    it("leaves the firm's other leads untouched", function () {
        $lead = privacyLead($this->franco);
        $neighbour = privacyLead($this->franco, ['email' => 'vizinho@exemplo.com.br']);

        $this->actingAs($this->manager)->postJson("/api/leads/{$lead->id}/anonymise")->assertOk();

        expect($neighbour->fresh()->email)->toBe('vizinho@exemplo.com.br')
            ->and($neighbour->fresh()->anonymised_at)->toBeNull();
    });
});
