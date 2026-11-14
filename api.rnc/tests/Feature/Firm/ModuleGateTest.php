<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

/*
 * A17 (rnc-4 R16, R17): a module the firm has off answers 404 on every route of it — the
 * `module:<key>` middleware, the signed-in person's firm deciding — while the core keeps
 * answering; turning it on opens the same routes. With `chat` off the diagnostic refuses the chat
 * channel with 422 `modules.disabled` and still starts from the form. With `brands` off no
 * `X-Brand` names a sub-brand.
 */
function gateFirm(array $modules): Firm
{
    return Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade', 'modules' => $modules]);
}

function gatePerson(Firm $firm, string $role, ?int $partnerId = null): User
{
    return User::factory()->create([
        'firm_id' => $firm->id,
        'role_id' => Role::where('slug', $role)->value('id'),
        'partner_id' => $partnerId,
    ]);
}

/** @return list<string> every opt-in key but those named */
function gateAllBut(string ...$off): array
{
    return array_values(array_diff(FirmModules::ALL, $off));
}

function gateSetModules(Firm $firm, array $modules): void
{
    $firm->forceFill(['modules' => $modules])->save();
}

beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class]));

describe('partners off (A17)', function () {
    beforeEach(function () {
        $this->firm = gateFirm(gateAllBut('partners'));
        $this->manager = gatePerson($this->firm, 'gestao');
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->firm->id, 'name' => 'Parceiro Exemplo', 'specialty' => 'erp_nfe', 'email' => 'p@parceiro.exemplo', 'active' => true,
        ]);
        $this->partnerUser = gatePerson($this->firm, 'parceiro', $partner->id);
    });

    it('answers 404 on the registry, the ledger and the portal while the pipeline answers', function () {
        $this->actingAs($this->manager)->getJson('/api/partners')->assertNotFound()->assertJsonPath('error', 'modules.disabled');
        $this->actingAs($this->manager)->getJson('/api/ledger')->assertNotFound();
        $this->actingAs($this->manager)->getJson('/api/referrals')->assertNotFound();
        $this->actingAs($this->manager)->getJson('/api/leads')->assertOk();
        $this->actingAs($this->partnerUser)->getJson('/api/portal/me')->assertNotFound();
    });

    it('opens the same routes once partners is turned on', function () {
        gateSetModules($this->firm, FirmModules::ALL);

        $this->actingAs($this->manager)->getJson('/api/partners')->assertOk();
        $this->actingAs($this->manager)->getJson('/api/ledger')->assertOk();
        $this->actingAs($this->partnerUser)->getJson('/api/portal/me')->assertOk();
    });

    it('closes the Radar’s batch referral, which needs partners as well as radar', function () {
        $this->actingAs($this->manager)->postJson('/api/radar/referrals', [])->assertNotFound();
    });
});

describe('each module’s own routes (R17)', function () {
    it('answers 404 with its module off and 200 with it on', function (string $module, string $uri) {
        $firm = gateFirm(gateAllBut($module));
        $operator = gatePerson($firm, 'operador');

        $this->actingAs($operator)->getJson($uri)->assertNotFound()->assertJsonPath('error', 'modules.disabled');

        gateSetModules($firm, FirmModules::ALL);

        $this->actingAs($operator)->getJson($uri)->assertOk();
    })->with([
        'portfolio' => ['portfolio', '/api/repricing'],
        'portfolio import' => ['portfolio', '/api/clients/import/template'],
        'fiscal' => ['fiscal', '/api/fiscal/readiness'],
        'radar' => ['radar', '/api/radar'],
        'contracts' => ['contracts', '/api/contracts'],
        'assistant' => ['assistant', '/api/assistant/notes'],
        'briefing' => ['briefing', '/api/briefing'],
        'brands' => ['brands', '/api/brands'],
        'channel' => ['channel', '/api/channel'],
    ]);
});

describe('the chat channel (A17)', function () {
    function gateStart(string $channel): TestResponse
    {
        return test()->withHeader('X-Firm', 'alvorada')->postJson('/api/public/diagnostics', [
            'visitor' => '0b7c9a3e-4d2f-4e8a-9c1b-2f3e4d5a6b7d',
            'path' => 'quiz',
            'source' => 'organico',
            'channel' => $channel,
        ]);
    }

    it('refuses a chat start with 422 modules.disabled while the form still starts', function () {
        gateFirm(gateAllBut('chat'));

        gateStart('chat')->assertUnprocessable()->assertJsonValidationErrors(['channel' => 'modules.disabled']);
        gateStart('formulario')->assertCreated();
    });

    it('starts from the chat once chat is on', function () {
        gateFirm(FirmModules::ALL);

        gateStart('chat')->assertCreated();
    });
});

describe('sub-brands off (R17)', function () {
    beforeEach(function () {
        $this->franco = Firm::where('slug', 'franco')->firstOrFail();
        FirmBrand::factory()->create(['firm_id' => $this->franco->id, 'slug' => 'express']);
    });

    it('names the sub-brand while brands is on', function () {
        $this->withHeader('X-Brand', 'express')->getJson('/api/public/firm')->assertOk()->assertJsonPath('data.brand', 'express');
    });

    it('knows no sub-brand with brands off — the header is as unknown as a typo', function () {
        gateSetModules($this->franco, gateAllBut('brands'));

        $this->withHeader('X-Brand', 'express')->getJson('/api/public/firm')->assertNotFound()->assertJsonPath('error', 'brands.notFound');
    });
});
