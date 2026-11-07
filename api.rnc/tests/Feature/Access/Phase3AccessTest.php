<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantAsk;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Models\AssistantQuestion;
use App\Modules\Auth\Models\Role;
use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

/*
 * A25 (rnc-3 R28; plan P10, AM14): Phase 3's routes, phase by phase — who is refused (a partner
 * and the team: 403; a guest: 401) and what another firm's row answers (404, never 403). The route
 * matrix (`RouteMatrixTest`) sweeps every staff route for the partner; this file names Phase 3's
 * rows on purpose, so a key granted too widely in a later phase fails here by name.
 *
 * Each phase appends its rows: F1 the brands; F3a the channel; F4 the assistant; F5 the questions' inbox; F6 the contracts;
 * F8 the briefing. F9 checks the file covers every route F1–F8 added.
 */
beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, BrandSeeder::class, ChannelSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->express = FirmBrand::withoutGlobalScopes()->where('slug', 'express')->firstOrFail();
    $this->channelFirm = ChannelFirm::withoutGlobalScopes()->where('stage', 'implantacao')->firstOrFail();
});

function phase3Person(string $role, ?int $partnerId = null): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
        'partner_id' => $partnerId,
    ]);
}

function phase3Partner(): User
{
    $partner = Partner::withoutGlobalScopes()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'name' => 'Andrade Advocacia (exemplo)',
        'specialty' => 'advocacia_tributaria',
        'email' => 'contato@andrade.exemplo',
        'active' => true,
    ]);

    return phase3Person('parceiro', $partner->id);
}

/** F1 — the brands (`brands.manage`, operador only: D3). */
dataset('phase 3 brand routes', [
    'GET brands' => ['GET', '/api/brands'],
    'PATCH brands/{id}' => ['PATCH', '/api/brands/{id}'],
    'POST brands/{id}/activate' => ['POST', '/api/brands/{id}/activate'],
    'POST brands/{id}/deactivate' => ['POST', '/api/brands/{id}/deactivate'],
]);

describe('F1 brands (A25)', function () {
    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, str_replace('{id}', (string) $this->express->id, $uri))->assertForbidden();
    })->with('phase 3 brand routes');

    it('refuses the team with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Person('equipe'))->json($method, str_replace('{id}', (string) $this->express->id, $uri))->assertForbidden();
    })->with('phase 3 brand routes');

    it('refuses Reinaldo with 403 (D3: the operator edits brands)', function (string $method, string $uri) {
        $this->actingAs(phase3Person('ceo'))->json($method, str_replace('{id}', (string) $this->express->id, $uri))->assertForbidden();
    })->with('phase 3 brand routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, str_replace('{id}', (string) $this->express->id, $uri))->assertUnauthorized();
    })->with('phase 3 brand routes');

    it("answers 404 on firm B's brand, and lists none of firm B's", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = FirmBrand::factory()->create(['firm_id' => $firmB->id, 'slug' => 'b-express', 'name' => 'Firma B Express']);
        $operator = phase3Person('operador');

        $this->actingAs($operator)->patchJson("/api/brands/{$theirs->id}", ['tagline' => 'roubada'])->assertNotFound();
        $this->actingAs($operator)->postJson("/api/brands/{$theirs->id}/deactivate")->assertNotFound();

        $slugs = collect($this->actingAs($operator)->getJson('/api/brands')->assertOk()->json('data.brands'))->pluck('slug')->all();

        expect($slugs)->toBe(['express', 'prime'])
            ->and(FirmBrand::withoutGlobalScopes()->findOrFail($theirs->id)->active)->toBeTrue();
    });
});

/** F3a — the RNC channel (`channel.read` ceo + operador, `channel.manage` operador only: R28, R9). */
dataset('phase 3 channel routes', [
    'GET channel' => ['GET', '/api/channel', true],
    'POST channel/firms' => ['POST', '/api/channel/firms', false],
    'PATCH channel/firms/{id}' => ['PATCH', '/api/channel/firms/{id}', false],
    'POST channel/firms/{id}/advance' => ['POST', '/api/channel/firms/{id}/advance', false],
]);

describe('F3a channel (A25)', function () {
    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, str_replace('{id}', (string) $this->channelFirm->id, $uri))->assertForbidden();
    })->with('phase 3 channel routes');

    it('refuses the team with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Person('equipe'))->json($method, str_replace('{id}', (string) $this->channelFirm->id, $uri))->assertForbidden();
    })->with('phase 3 channel routes');

    it('lets Reinaldo read and refuses him every write with 403 (R9)', function (string $method, string $uri, bool $read) {
        $response = $this->actingAs(phase3Person('ceo'))->json($method, str_replace('{id}', (string) $this->channelFirm->id, $uri), ['to' => 'ativo']);

        $read ? $response->assertOk() : $response->assertForbidden();
    })->with('phase 3 channel routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, str_replace('{id}', (string) $this->channelFirm->id, $uri))->assertUnauthorized();
    })->with('phase 3 channel routes');

    it("answers 404 on firm B's channel firm, and lists none of firm B's", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = ChannelFirm::factory()->create(['firm_id' => $firmB->id, 'name' => 'Firma B Parceira (exemplo)', 'stage' => 'contato']);
        $operator = phase3Person('operador');

        $this->actingAs($operator)->patchJson("/api/channel/firms/{$theirs->id}", ['notes' => 'roubada'])->assertNotFound();
        $this->actingAs($operator)->postJson("/api/channel/firms/{$theirs->id}/advance", ['to' => 'demo'])->assertNotFound();

        $names = collect($this->actingAs($operator)->getJson('/api/channel')->assertOk()->json('data.firms'))->pluck('name');

        expect($names)->toHaveCount(6)
            ->and($names)->not->toContain('Firma B Parceira (exemplo)')
            ->and(ChannelFirm::withoutGlobalScopes()->findOrFail($theirs->id)->stage)->toBe('contato');
    });
});

/** F4 — the Reforma assistant (`assistant.ask` equipe + ceo, `assistant.manage` ceo + operador: R28, AM4). */
dataset('phase 3 assistant routes', [
    'POST assistant/ask' => ['POST', '/api/assistant/ask', false],
    'GET assistant/suggestions' => ['GET', '/api/assistant/suggestions', false],
    'POST assistant/questions' => ['POST', '/api/assistant/questions', false],
    'GET assistant/notes' => ['GET', '/api/assistant/notes', false],
    'POST assistant/notes' => ['POST', '/api/assistant/notes', false],
    'PATCH assistant/notes/{id}' => ['PATCH', '/api/assistant/notes/{id}', false],
    'POST assistant/notes/{id}/review' => ['POST', '/api/assistant/notes/{id}/review', false],
    'POST assistant/notes/{id}/retire' => ['POST', '/api/assistant/notes/{id}/retire', true],
    'GET assistant/gaps' => ['GET', '/api/assistant/gaps', true],
]);

describe('F4 assistant (A25)', function () {
    beforeEach(function () {
        $this->note = AssistantNote::factory()->create(['firm_id' => $this->franco->id]);
    });

    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, str_replace('{id}', (string) $this->note->id, $uri))->assertForbidden();
    })->with('phase 3 assistant routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, str_replace('{id}', (string) $this->note->id, $uri))->assertUnauthorized();
    })->with('phase 3 assistant routes');

    it('refuses the team, a specialist too, with 403 on the assistant.manage routes (retire, gaps)', function (string $method, string $uri, bool $manage) {
        $response = $this->actingAs(assistantPerson('equipe', specialist: true))->json($method, str_replace('{id}', (string) $this->note->id, $uri));

        $manage ? $response->assertForbidden() : expect($response->status())->not->toBe(403);
    })->with('phase 3 assistant routes');

    it('lets the team ask, see the chips and hand a question off (assistant.ask)', function () {
        assistantPerson('equipe', specialist: true);
        $team = phase3Person('equipe');

        $this->actingAs($team)->postJson('/api/assistant/ask', ['question' => 'qual o melhor ERP'])->assertOk();
        $this->actingAs($team)->getJson('/api/assistant/suggestions')->assertOk();
        $this->actingAs($team)->postJson('/api/assistant/questions', ['question' => 'qual o melhor ERP'])->assertCreated();
    });

    it("answers 404 on firm B's note, and lists none of firm B's", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = AssistantNote::factory()->create(['firm_id' => $firmB->id, 'slug' => 'deles']);
        $operator = phase3Person('operador');

        $this->actingAs($operator)->patchJson("/api/assistant/notes/{$theirs->id}", ['answer' => 'roubada'])->assertNotFound();
        $this->actingAs($operator)->postJson("/api/assistant/notes/{$theirs->id}/retire")->assertNotFound();
        $this->actingAs(assistantPerson('equipe', specialist: true))->postJson("/api/assistant/notes/{$theirs->id}/review")->assertNotFound();

        $slugs = collect($this->actingAs($operator)->getJson('/api/assistant/notes')->assertOk()->json('data'))->pluck('slug');

        expect($slugs)->not->toContain('deles')
            ->and(AssistantNote::withoutGlobalScopes()->findOrFail($theirs->id)->status)->toBe('revisada');
    });

    it("offers none of firm B's notes as a chip and lists none of firm B's asks as a gap (F9)", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = AssistantNote::factory()->create(['firm_id' => $firmB->id, 'slug' => 'deles', 'question' => 'Pergunta da firma B?']);
        AssistantAsk::withoutGlobalScopes()->forceCreate([
            'firm_id' => $firmB->id, 'user_id' => phase3Person('equipe')->id, 'question' => 'Pergunta sem nota da firma B',
            'normalised' => 'pergunta sem nota da firma b', 'note_id' => null, 'at' => now(),
        ]);
        $operator = phase3Person('operador');

        $chips = collect($this->actingAs($operator)->getJson('/api/assistant/suggestions')->assertOk()->json('data'))->pluck('id');
        $gaps = collect($this->actingAs($operator)->getJson('/api/assistant/gaps')->assertOk()->json('data'))->pluck('normalised');

        expect($chips)->toContain($this->note->id)
            ->and($chips)->not->toContain($theirs->id)
            ->and($gaps)->not->toContain('pergunta sem nota da firma b');
    });
});

/** F5 — the questions' inbox and the answer (`assistant.ask`; who sees and answers is the service's: R14). */
dataset('phase 3 question routes', [
    'GET assistant/questions' => ['GET', '/api/assistant/questions'],
    'POST assistant/questions/{id}/answer' => ['POST', '/api/assistant/questions/{id}/answer'],
]);

describe('F5 questions (A25)', function () {
    beforeEach(function () {
        $this->specialist = assistantPerson('equipe', specialist: true);
        $this->question = AssistantQuestion::withoutGlobalScopes()->forceCreate([
            'firm_id' => $this->franco->id,
            'asked_by' => phase3Person('equipe')->id,
            'specialist_id' => $this->specialist->id,
            'question' => 'Qual o melhor ERP?',
            'status' => 'aberta',
        ]);
    });

    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, str_replace('{id}', (string) $this->question->id, $uri))->assertForbidden();
    })->with('phase 3 question routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, str_replace('{id}', (string) $this->question->id, $uri))->assertUnauthorized();
    })->with('phase 3 question routes');

    it('lets the assigned specialist read the inbox and answer', function () {
        $this->actingAs($this->specialist)->getJson('/api/assistant/questions')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->specialist)->postJson("/api/assistant/questions/{$this->question->id}/answer", ['answer' => 'Depende.'])->assertOk();
    });

    it("answers 404 on firm B's question, and lists none of firm B's", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = AssistantQuestion::withoutGlobalScopes()->forceCreate([
            'firm_id' => $firmB->id,
            'asked_by' => $this->specialist->id,
            'specialist_id' => $this->specialist->id,
            'question' => 'De outra firma?',
            'status' => 'aberta',
        ]);

        $this->actingAs($this->specialist)->postJson("/api/assistant/questions/{$theirs->id}/answer", ['answer' => 'Roubada.'])->assertNotFound();

        $ids = collect($this->actingAs(phase3Person('operador'))->getJson('/api/assistant/questions')->assertOk()->json('data'))->pluck('id');

        expect($ids->all())->toBe([$this->question->id])
            ->and(AssistantQuestion::withoutGlobalScopes()->findOrFail($theirs->id)->status)->toBe('aberta');
    });
});

/** F6 — the contract alert (`contracts.manage`, ceo + operador: R18, R28). */
dataset('phase 3 contract routes', [
    'GET contracts' => ['GET', '/api/contracts'],
    'POST contracts/import' => ['POST', '/api/contracts/import'],
    'GET contracts/template' => ['GET', '/api/contracts/template'],
    'POST contracts/{id}/clause' => ['POST', '/api/contracts/{id}/clause'],
    // F6b — the legal referral (R20).
    'GET contracts/legal-partners' => ['GET', '/api/contracts/legal-partners'],
    'POST contracts/{id}/referrals' => ['POST', '/api/contracts/{id}/referrals'],
]);

describe('F6 contracts (A25)', function () {
    beforeEach(function () {
        $client = Client::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id, 'name' => 'Acme Ltda', 'cnpj' => '11222333000181', 'sector' => 'comercio',
            'regime' => 'presumido', 'band' => 'b3', 'b2b_share' => 70, 'monthly_fee' => 3000,
        ]);
        $this->contract = ClientContract::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id, 'client_id' => $client->id, 'kind' => 'locacao',
            'annual_value_cents' => 50_000_000, 'start_year' => 2024, 'end_year' => 2031, 'has_reforma_clause' => false,
        ]);
    });

    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, str_replace('{id}', (string) $this->contract->id, $uri))->assertForbidden();
    })->with('phase 3 contract routes');

    it('refuses the team with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Person('equipe'))->json($method, str_replace('{id}', (string) $this->contract->id, $uri))->assertForbidden();
    })->with('phase 3 contract routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, str_replace('{id}', (string) $this->contract->id, $uri))->assertUnauthorized();
    })->with('phase 3 contract routes');

    it('lets Reinaldo read the alert and mark a clause', function () {
        $reinaldo = phase3Person('ceo');

        $this->actingAs($reinaldo)->getJson('/api/contracts')->assertOk()->assertJsonPath('data.summary.at_risk', 1);
        $this->actingAs($reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();
    });

    it("answers 404 on firm B's contract, lists none of firm B's and never imports onto firm B's client", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirClient = Client::withoutGlobalScopes()->create([
            'firm_id' => $firmB->id, 'name' => 'Cliente da Firma B', 'cnpj' => '44555666000181', 'sector' => 'comercio',
            'regime' => 'real', 'band' => 'b4', 'b2b_share' => 50, 'monthly_fee' => 5000,
        ]);
        $theirs = ClientContract::withoutGlobalScopes()->create([
            'firm_id' => $firmB->id, 'client_id' => $theirClient->id, 'kind' => 'fornecimento',
            'annual_value_cents' => 90_000_000, 'start_year' => 2024, 'end_year' => 2033, 'has_reforma_clause' => false,
        ]);
        $reinaldo = phase3Person('ceo');

        $this->actingAs($reinaldo)->postJson("/api/contracts/{$theirs->id}/clause")->assertNotFound();
        $lawyer = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id, 'name' => 'Andrade (exemplo)', 'specialty' => 'advocacia_tributaria', 'email' => 'andrade@parceiro.exemplo', 'active' => true,
        ]);
        $theirClient->forceFill(['partner_consent' => true, 'partner_consent_via' => 'contrato'])->save();
        $this->actingAs($reinaldo)->postJson("/api/contracts/{$theirs->id}/referrals", ['partner_id' => $lawyer->id])->assertNotFound();

        $alert = $this->actingAs($reinaldo)->getJson('/api/contracts')->assertOk()->json('data');
        $csv = UploadedFile::fake()->createWithContent('contratos.csv', "cnpj_cliente;tipo;valor_anual;inicio;fim;clausula_reforma\n44555666000181;locacao;100000;2024;2030;sim\n");
        $this->actingAs($reinaldo)->post('/api/contracts/import', ['file' => $csv], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.errors.0.code', 'client_unknown');

        expect(array_column($alert['contracts'], 'id'))->toBe([$this->contract->id])
            ->and($alert['summary']['total'])->toBe(1)
            ->and(ClientContract::withoutGlobalScopes()->findOrFail($theirs->id)->has_reforma_clause)->toBeFalse()
            ->and(ClientContract::withoutGlobalScopes()->where('client_id', $theirClient->id)->count())->toBe(1);
    });

    it("lists none of firm B's legal partners in the picker (F9)", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $ours = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id, 'name' => 'Andrade (exemplo)', 'specialty' => 'advocacia_tributaria', 'email' => 'andrade@parceiro.exemplo', 'active' => true,
        ]);
        Partner::withoutGlobalScopes()->create([
            'firm_id' => $firmB->id, 'name' => 'Advocacia da Firma B', 'specialty' => 'advocacia_tributaria', 'email' => 'adv@firmab.exemplo', 'active' => true,
        ]);

        $ids = collect($this->actingAs(phase3Person('ceo'))->getJson('/api/contracts/legal-partners')->assertOk()->json('data'))->pluck('id');

        expect($ids->all())->toBe([$ours->id]);
    });
});

/** F8 — Reinaldo's briefing (`briefing.read`, ceo + operador: R23, R28). */
dataset('phase 3 briefing routes', [
    'GET briefing' => ['GET', '/api/briefing'],
]);

describe('F8 briefing (A25)', function () {
    it('refuses a partner with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Partner())->json($method, $uri)->assertForbidden();
    })->with('phase 3 briefing routes');

    it('refuses the team with 403', function (string $method, string $uri) {
        $this->actingAs(phase3Person('equipe'))->json($method, $uri)->assertForbidden();
    })->with('phase 3 briefing routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized();
    })->with('phase 3 briefing routes');

    it("lets Reinaldo and the operator read it, and counts none of firm B's leads, meetings or referrals", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        Lead::factory()->create(['firm_id' => $firmB->id, 'stage' => 'proposta']);
        Lead::factory()->create(['firm_id' => $this->franco->id, 'stage' => 'proposta']);

        $briefing = $this->actingAs(phase3Person('ceo'))->getJson('/api/briefing')->assertOk()->json('data');
        $this->actingAs(phase3Person('operador'))->getJson('/api/briefing')->assertOk();

        expect($briefing['leads']['last_7_days'])->toBe(1)
            ->and($briefing['pipeline']['count'])->toBe(1)
            ->and($briefing['referrals']['count'])->toBe(0);
    });
});

/*
 * F9 — A25 complete (plan AM14): every route served by a Phase 3 controller is a row of one of this
 * file's datasets, so it has its partner, team and guest lines by name. A route added to one of these
 * controllers without a row here fails this test, not a later review.
 */
const PHASE3_CONTROLLERS = [
    'App\Modules\Firm\Controllers\BrandController@',
    'App\Modules\Channel\Controllers\ChannelController@',
    'App\Modules\Assistant\Controllers\AssistantController@',
    'App\Modules\Assistant\Controllers\NoteController@',
    'App\Modules\Portfolio\Controllers\ContractController@',
    'App\Modules\Partner\Controllers\ContractReferralController@',
    'App\Modules\Briefing\Controllers\BriefingController@',
];

it("names every route of Phase 3's controllers in a dataset of this file (A25 complete)", function () {
    $source = (string) file_get_contents(__FILE__);

    // Every verb of every route (review A4): `GET` and `POST assistant/questions` share a uri, not a line.
    $phase3 = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => array_filter(PHASE3_CONTROLLERS, fn (string $prefix) => str_starts_with($route->getActionName(), $prefix)) !== [])
        ->flatMap(fn ($route) => array_map(fn (string $method) => $method.' '.substr($route->uri(), strlen('api/')), array_values(array_diff($route->methods(), ['HEAD']))))
        ->values();

    // A row is read by what it requests — its method and its uri — not by its name: a row named
    // "GET x" that sends POST would leave GET x untested.
    preg_match_all("~^\\s*'([A-Z]+ [^']+)' => \\['([A-Z]+)', '/api/([^']+)'~m", $source, $rows, PREG_SET_ORDER);
    $requested = collect($rows)->map(fn (array $row) => "{$row[2]} {$row[3]}");
    $misnamed = collect($rows)->filter(fn (array $row) => $row[1] !== "{$row[2]} {$row[3]}")->map(fn (array $row) => $row[1])->values()->all();

    $unnamed = $phase3->diff($requested)->values()->all();

    expect($phase3)->toHaveCount(26)
        ->and($misnamed)->toBe([])
        ->and($unnamed)->toBe([]);
});
