<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use Database\Seeders\FirmSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
 * The public diagnostic (rnc-1 F3, R9–R14, plan AM3/AM10/AM12/AM13):
 *
 *   POST  /api/public/diagnostics                      step 1 starts → a `visitante` lead + its token
 *   POST  /api/public/diagnostics/{token}/cnpj         the CNPJ path's BrasilAPI lookup (10/min/IP)
 *   PATCH /api/public/diagnostics/{token}              the contact (→ lead_parcial) and later steps
 *   GET   /api/public/diagnostics/{token}/result       count, titles, ranges — never a detail (→ lead)
 *   GET   /api/public/diagnostics/{token}/simulation   2026–2033 tax mix + split payment
 *
 * Every answer goes through `PublicLeadResource`'s allow-list: never phone, e-mail, contact name,
 * or the internal id. An unknown or expired token is a 404, and a lead past `reuniao_marcada`
 * refuses a profile change (409).
 */
const DIAGNOSTIC_VISITOR = '6f1c2a4e-8b3d-4c5a-9e7f-1a2b3c4d5e6f';
const VALID_CNPJ = '11222333000181';
const CONSENT_TEXT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';

function startDiagnostic(array $overrides = []): string
{
    $response = test()->postJson('/api/public/diagnostics', array_merge([
        'visitor' => DIAGNOSTIC_VISITOR,
        'path' => 'quiz',
        'source' => 'pago',
        'medium' => 'cpc',
    ], $overrides))->assertCreated();

    return $response->json('data.token');
}

function contactPayload(array $overrides = []): array
{
    return ['contact' => array_merge([
        'company' => 'Clínica Horizonte',
        'name' => 'Ana Souza',
        'phone' => '(11) 98888-7777',
        'email' => 'Ana@Horizonte.com.br',
        'consent' => true,
    ], $overrides), 'step' => 'perfil'];
}

function completeProfile(string $token, array $profile = []): void
{
    test()->patchJson("/api/public/diagnostics/{$token}", ['profile' => array_merge([
        'sector' => 'servicos',
        'regime' => 'simples',
        'b2b' => 80,
        'band' => 'b2',
        'incentivos' => false,
        'exporta' => false,
    ], $profile), 'step' => 'resultado'])->assertOk();
}

function leadOf(string $token): Lead
{
    return Lead::withoutGlobalScopes()->where('token', $token)->sole();
}

/** @param  array<string, mixed>  $json */
function assertNoContactData(array $json): void
{
    $flat = json_encode($json);

    expect($flat)->not->toContain('98888')
        ->not->toContain('horizonte.com.br')
        ->not->toContain('Ana Souza')
        ->and(Arr::dot($json))->not->toHaveKeys(['data.id', 'data.phone', 'data.email', 'data.contact_name', 'data.visitor_uuid']);
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    $this->seed(FirmSeeder::class);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

describe('starting (step 1)', function () {
    it('creates a visitante lead with no personal data and answers its token (AM3, AM13)', function () {
        $response = $this->postJson('/api/public/diagnostics', [
            'visitor' => DIAGNOSTIC_VISITOR,
            'path' => 'quiz',
            'sector' => 'saude',
            'source' => 'pago',
            'medium' => 'cpc',
        ])->assertCreated();

        $lead = Lead::withoutGlobalScopes()->sole();

        expect($response->json('data.token'))->toBe($lead->token)
            ->and(strlen($lead->token))->toBe(48)
            ->and($lead->token_expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($lead->firm_id)->toBe($this->franco->id)
            ->and($lead->stage)->toBe('visitante')
            ->and($lead->last_step)->toBe('empresa')
            ->and($lead->path)->toBe('quiz')
            ->and($lead->visitor_uuid)->toBe(DIAGNOSTIC_VISITOR)
            ->and($lead->source)->toBe('pago')
            ->and($lead->medium)->toBe('cpc')
            ->and($lead->channel)->toBe('formulario')
            ->and($lead->profile['sector'])->toBe('saude')
            ->and($lead->phone)->toBeNull()
            ->and($lead->email)->toBeNull();

        expect($response->json('data'))->toMatchArray([
            'stage' => 'visitante',
            'last_step' => 'empresa',
            'path' => 'quiz',
            'has_contact' => false,
            'consent' => ['text' => CONSENT_TEXT, 'version' => 1],
        ]);

        expect(LeadEvent::query()->where('lead_id', $lead->id)->pluck('type')->all())->toBe(['diagnostico.inicio']);
    });

    it('refuses a path, a source or a sector it does not know', function () {
        $this->postJson('/api/public/diagnostics', ['path' => 'chat', 'source' => 'tv', 'sector' => 'mineracao'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['path', 'source', 'sector']);
    });

    it('never takes a stage, a firm or a token from the body', function () {
        startDiagnostic(['stage' => 'cliente', 'firm_id' => 99, 'token' => 'chosen-by-the-client']);

        $lead = Lead::withoutGlobalScopes()->sole();

        expect($lead->stage)->toBe('visitante')
            ->and($lead->firm_id)->toBe($this->franco->id)
            ->and($lead->token)->not->toBe('chosen-by-the-client');
    });
});

describe('the CNPJ path (R9, A5)', function () {
    it('fills name, sector, band and Simples from BrasilAPI', function () {
        Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response([
            'cnpj' => VALID_CNPJ,
            'razao_social' => 'MERCADO BOA VISTA LTDA',
            'cnae_fiscal' => 4711302,
            'cnae_fiscal_descricao' => 'Comércio varejista de mercadorias em geral',
            'codigo_porte' => 3,
            'porte' => 'EMPRESA DE PEQUENO PORTE',
            'opcao_pelo_simples' => true,
            'opcao_pelo_mei' => false,
        ])]);
        $token = startDiagnostic(['path' => 'cnpj']);

        $response = $this->postJson("/api/public/diagnostics/{$token}/cnpj", ['cnpj' => '11.222.333/0001-81'])->assertOk();

        expect($response->json('data.lookup'))->toBe('found')
            ->and($response->json('data.company'))->toBe([
                'name' => 'MERCADO BOA VISTA LTDA',
                'cnae' => '4711302',
                'cnae_description' => 'Comércio varejista de mercadorias em geral',
                'porte' => 'EMPRESA DE PEQUENO PORTE',
                'sector' => 'comercio',
                'band' => 'b2',
                'regime' => 'simples',
            ])
            ->and($response->json('data.lead.company'))->toBe('MERCADO BOA VISTA LTDA')
            ->and($response->json('data.lead.profile'))->toMatchArray(['sector' => 'comercio', 'band' => 'b2', 'regime' => 'simples']);

        $lead = leadOf($token);

        expect($lead->cnpj)->toBe(VALID_CNPJ)->and($lead->company)->toBe('MERCADO BOA VISTA LTDA');
    });

    it('answers 422 for a wrong check digit, before calling BrasilAPI', function () {
        Http::fake();
        $token = startDiagnostic(['path' => 'cnpj']);

        $this->postJson("/api/public/diagnostics/{$token}/cnpj", ['cnpj' => '11.222.333/0001-82'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj']);

        Http::assertNothingSent();
    });

    it('falls back to the quiz when BrasilAPI does not know the CNPJ or times out', function (Closure $fake) {
        $fake();
        $token = startDiagnostic(['path' => 'cnpj']);

        $response = $this->postJson("/api/public/diagnostics/{$token}/cnpj", ['cnpj' => VALID_CNPJ])->assertOk();

        expect($response->json('data.lookup'))->toBe('unavailable')
            ->and($response->json('data.company'))->toBeNull()
            ->and(leadOf($token)->cnpj)->toBe(VALID_CNPJ);
    })->with([
        'not found' => fn () => Http::fake(['brasilapi.com.br/*' => Http::response(['message' => 'não encontrado'], 404)]),
        'timeout' => fn () => Http::fake(fn () => throw new ConnectionException('timed out')),
    ]);

    it('is throttled to 10 lookups a minute per address (AM10)', function () {
        Http::fake(['brasilapi.com.br/*' => Http::response(['message' => 'não encontrado'], 404)]);
        $token = startDiagnostic(['path' => 'cnpj']);

        foreach (range(1, 10) as $attempt) {
            $this->postJson("/api/public/diagnostics/{$token}/cnpj", ['cnpj' => VALID_CNPJ])->assertOk();
        }

        $this->postJson("/api/public/diagnostics/{$token}/cnpj", ['cnpj' => VALID_CNPJ])->assertTooManyRequests();
    });
});

describe('the contact, right after step 1 (R11, A6)', function () {
    it('turns the lead into a lead parcial with its last step, even if the visitor never comes back', function () {
        $token = startDiagnostic();

        $response = $this->patchJson("/api/public/diagnostics/{$token}", contactPayload())->assertOk();

        $lead = leadOf($token);

        expect($lead->stage)->toBe('lead_parcial')
            ->and($lead->last_step)->toBe('perfil')
            ->and($lead->company)->toBe('Clínica Horizonte')
            ->and($lead->contact_name)->toBe('Ana Souza')
            ->and($lead->phone)->toBe('5511988887777')
            ->and($lead->email)->toBe('ana@horizonte.com.br')
            ->and($response->json('data.stage'))->toBe('lead_parcial')
            ->and($response->json('data.has_contact'))->toBeTrue();

        assertNoContactData($response->json());

        expect(LeadEvent::query()->where('lead_id', $lead->id)->pluck('type')->all())->toBe(['diagnostico.inicio', 'contato.capturado', 'diagnostico.passo']);
    });

    it('stores the consent with the text, version, time and channel — given (AM12)', function () {
        $this->freezeSecond();
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['consent' => true]))->assertOk();

        $consent = LeadConsent::query()->sole();

        expect($consent->lead_id)->toBe(leadOf($token)->id)
            ->and($consent->given)->toBeTrue()
            ->and($consent->text)->toBe(CONSENT_TEXT)
            ->and($consent->version)->toBe(1)
            ->and($consent->via)->toBe('formulario')
            ->and($consent->given_at->equalTo(now()))->toBeTrue()
            ->and(array_keys($consent->getAttributes()))->not->toContain('ip', 'user_agent');
    });

    it('stores a refusal too, since the box starts unticked', function () {
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['consent' => false]))->assertOk();

        expect(LeadConsent::query()->sole()->given)->toBeFalse();
    });

    it('needs the company and one contact, phone or e-mail', function () {
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['company' => '', 'phone' => null, 'email' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact.company', 'contact.phone']);

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['phone' => null]))->assertOk();
        expect(leadOf($token)->phone)->toBeNull();
    });

    it('refuses a phone or an e-mail that is not one', function () {
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['phone' => '1234', 'email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact.phone', 'contact.email']);
    });

    it('needs the consent answered, true or false', function () {
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload(['consent' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact.consent']);
    });
});

describe('the later steps', function () {
    it('merges the answers over the stored profile and moves the last step', function () {
        $token = startDiagnostic(['sector' => 'saude']);
        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload())->assertOk();

        $this->patchJson("/api/public/diagnostics/{$token}", ['profile' => ['regime' => 'presumido', 'b2b' => 30], 'step' => 'faturamento'])->assertOk();

        $lead = leadOf($token);

        expect($lead->profile)->toMatchArray(['sector' => 'saude', 'regime' => 'presumido', 'b2b' => 30])
            ->and($lead->last_step)->toBe('faturamento')
            ->and($lead->stage)->toBe('lead_parcial');
    });

    it('refuses values outside the quiz', function () {
        $token = startDiagnostic();

        $this->patchJson("/api/public/diagnostics/{$token}", ['profile' => ['sector' => 'x', 'regime' => 'mei', 'band' => 'b9', 'b2b' => 120, 'incentivos' => 'talvez'], 'step' => 'fim'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile.sector', 'profile.regime', 'profile.band', 'profile.b2b', 'profile.incentivos', 'step']);
    });

    it('refuses a profile change once the meeting is booked (409)', function () {
        $token = startDiagnostic();
        leadOf($token)->forceFill(['stage' => 'reuniao_marcada'])->save();

        $this->patchJson("/api/public/diagnostics/{$token}", ['profile' => ['regime' => 'real']])
            ->assertConflict()
            ->assertJsonPath('code', 'lead_locked');
    });
});

describe('the result (R12, R13, A7)', function () {
    it('counts and names the items with their ranges, and never sends a detail', function () {
        $token = startDiagnostic();
        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload())->assertOk();
        completeProfile($token);

        $response = $this->getJson("/api/public/diagnostics/{$token}/result")->assertOk();

        expect($response->json('data.opportunities'))->toBeGreaterThanOrEqual(3)
            ->and($response->json('data.risks'))->toBe(2)
            ->and($response->json('data.items.0'))->toBe([
                'kind' => 'oportunidade',
                'title' => 'Opção pelo regime regular do IBS/CBS',
                'range_min' => 18000,
                'range_max' => 54000,
                'range_label' => 'R$ 18–54 mil/ano',
            ])
            ->and($response->json('data.company'))->toBe('Clínica Horizonte');

        $body = $response->getContent();

        // Neither the demo's detail sentence nor the cited rule of any item leaves the API.
        expect($body)->not->toContain('detail')
            ->not->toContain('Clientes PJ tomam crédito integral')
            ->not->toContain('LC 214/2025');

        assertNoContactData($response->json());
    });

    it('turns the lead parcial into a lead (AM13)', function () {
        $token = startDiagnostic();
        $this->patchJson("/api/public/diagnostics/{$token}", contactPayload())->assertOk();
        completeProfile($token);

        $this->getJson("/api/public/diagnostics/{$token}/result")->assertOk();
        $this->getJson("/api/public/diagnostics/{$token}/result")->assertOk();

        $lead = leadOf($token);

        expect($lead->stage)->toBe('lead')
            ->and($lead->last_step)->toBe('resultado')
            ->and(LeadEvent::query()->where('lead_id', $lead->id)->where('type', 'resultado.visto')->count())->toBe(1);
    });

    it('answers 422 while sector, regime or band is missing', function () {
        $token = startDiagnostic();

        $this->getJson("/api/public/diagnostics/{$token}/result")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'profile_incomplete');
    });
});

describe('the simulation (R14, A8)', function () {
    it('returns the eight years, 2033 without PIS/Cofins, ICMS and ISS, and the split payment range', function () {
        $token = startDiagnostic();
        completeProfile($token, ['sector' => 'comercio', 'band' => 'b2']);

        $response = $this->getJson("/api/public/diagnostics/{$token}/simulation")->assertOk();

        $years = collect($response->json('data.years'));

        expect($years->pluck('year')->all())->toBe([2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033])
            ->and(array_column($years->last()['parts'], 'tax'))->toBe(['cbs', 'ibs'])
            ->and($response->json('data.split_payment'))->toBe(['min' => 15900, 'max' => 27825, 'label' => 'R$ 16–28 mil/mês'])
            ->and($response->json('data.estimate'))->toBeTrue();
    });
});

describe('the token (AM3)', function () {
    it('answers 404 for an unknown token on every route', function () {
        $unknown = Str::random(48);

        $this->patchJson("/api/public/diagnostics/{$unknown}", ['step' => 'perfil'])->assertNotFound();
        $this->postJson("/api/public/diagnostics/{$unknown}/cnpj", ['cnpj' => VALID_CNPJ])->assertNotFound();
        $this->getJson("/api/public/diagnostics/{$unknown}/result")->assertNotFound();
        $this->getJson("/api/public/diagnostics/{$unknown}/simulation")->assertNotFound();
    });

    it('answers 404 once the token is past its 30 days', function () {
        $token = startDiagnostic();
        completeProfile($token);

        $this->travel(31)->days();

        $this->getJson("/api/public/diagnostics/{$token}/result")->assertNotFound();
        $this->patchJson("/api/public/diagnostics/{$token}", ['step' => 'perfil'])->assertNotFound();
    });

    it("answers 404 for another firm's token (A1)", function () {
        Firm::factory()->create(['slug' => 'outra']);
        $token = $this->withHeader('X-Firm', 'outra')->postJson('/api/public/diagnostics', ['path' => 'quiz', 'source' => 'direto'])->assertCreated()->json('data.token');

        $this->withHeader('X-Firm', 'franco')->getJson("/api/public/diagnostics/{$token}/simulation")->assertNotFound();
        $this->withHeader('X-Firm', 'franco')->patchJson("/api/public/diagnostics/{$token}", ['step' => 'perfil'])->assertNotFound();
    });
});
