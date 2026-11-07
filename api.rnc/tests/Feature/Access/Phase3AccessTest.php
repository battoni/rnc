<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Channel\Models\ChannelFirm;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Models\FirmBrand;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\BrandSeeder;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A25 (rnc-3 R28; plan P10, AM14): Phase 3's routes, phase by phase — who is refused (a partner
 * and the team: 403; a guest: 401) and what another firm's row answers (404, never 403). The route
 * matrix (`RouteMatrixTest`) sweeps every staff route for the partner; this file names Phase 3's
 * rows on purpose, so a key granted too widely in a later phase fails here by name.
 *
 * Each phase appends its rows: F1 the brands; F3a the channel; F4 the assistant; F6 the contracts;
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
