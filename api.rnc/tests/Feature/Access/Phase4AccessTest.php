<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;

/*
 * rnc-4 (plan P4, P6): the white-label platform's routes, phase by phase — who is refused (a
 * partner, the team, a firm's manager: 403; a guest: 401). The route matrix (`RouteMatrixTest`)
 * sweeps every staff route for the partner; this file names rnc-4's rows on purpose, so a key
 * granted too widely in a later phase fails here by name.
 *
 * F4a: the operator's console (`platform.firms`, `platform.consent`) — the `operador` role with no
 * firm, and nobody else: not a firm account handed the key in its own permissions, and no role but
 * the operator's can be given it (`RoleService`).
 */
beforeEach(function () {
    config(['firms.default_slug' => PlatformFirmSeeder::PLATFORM_SLUG]);

    $this->seed([FirmSeeder::class, PlatformFirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', FirmSeeder::FRANCO_SLUG)->firstOrFail();
});

function phase4Person(string $role, ?int $firmId, ?int $partnerId = null): User
{
    return User::factory()->create([
        'firm_id' => $firmId,
        'role_id' => Role::where('slug', $role)->value('id'),
        'partner_id' => $partnerId,
    ]);
}

function phase4Url(string $uri): string
{
    return str_replace('{id}', (string) Firm::where('slug', FirmSeeder::FRANCO_SLUG)->value('id'), $uri);
}

/** F4a — the console (`platform.firms`, `platform.consent`; the operator alone, R24). */
dataset('phase 4 platform routes', [
    'GET platform/firms' => ['GET', '/api/platform/firms'],
    'POST platform/firms' => ['POST', '/api/platform/firms'],
    'PATCH platform/firms/{id}' => ['PATCH', '/api/platform/firms/{id}'],
    'PUT platform/firms/{id}/modules' => ['PUT', '/api/platform/firms/{id}/modules'],
    'POST platform/firms/{id}/manager' => ['POST', '/api/platform/firms/{id}/manager'],
    'GET platform/consent-default' => ['GET', '/api/platform/consent-default'],
    'PUT platform/consent-default' => ['PUT', '/api/platform/consent-default'],
]);

describe('F4a platform console', function () {
    it('refuses a partner with 403', function (string $method, string $uri) {
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id,
            'name' => 'Andrade Advocacia (exemplo)',
            'specialty' => 'advocacia_tributaria',
            'email' => 'contato@andrade.exemplo',
            'active' => true,
        ]);

        $this->actingAs(phase4Person('parceiro', $this->franco->id, $partner->id))->json($method, phase4Url($uri))->assertForbidden();
    })->with('phase 4 platform routes');

    it('refuses the team with 403', function (string $method, string $uri) {
        $this->actingAs(phase4Person('equipe', $this->franco->id))->json($method, phase4Url($uri))->assertForbidden();
    })->with('phase 4 platform routes');

    it("refuses a firm's manager with 403", function (string $method, string $uri) {
        $this->actingAs(phase4Person('gestao', $this->franco->id))->json($method, phase4Url($uri))->assertForbidden();
    })->with('phase 4 platform routes');

    it('refuses a firm account given the keys in its own permissions with 403', function (string $method, string $uri) {
        $person = phase4Person('gestao', $this->franco->id);
        foreach (['platform.firms', 'platform.consent'] as $key) {
            UserPermission::create(['user_id' => $person->id, 'permission_key' => $key, 'value' => true]);
        }

        $this->actingAs($person)->json($method, phase4Url($uri))->assertForbidden();
    })->with('phase 4 platform routes');

    it('refuses an operator account that belongs to a firm with 403', function (string $method, string $uri) {
        $this->actingAs(phase4Person('operador', $this->franco->id))->json($method, phase4Url($uri))->assertForbidden();
    })->with('phase 4 platform routes');

    it('asks a guest to sign in', function (string $method, string $uri) {
        $this->json($method, phase4Url($uri))->assertUnauthorized();
    })->with('phase 4 platform routes');

    it('lets the firm-less operator through (validation or success, never 401, 403 or 404)', function (string $method, string $uri) {
        $status = $this->actingAs(phase4Person('operador', null))->json($method, phase4Url($uri))->status();

        expect($status)->not->toBeIn([401, 403, 404]);
    })->with('phase 4 platform routes');

    it("refuses to give a platform key to any role but the operator's", function (string $key) {
        $operator = phase4Person('operador', null);
        $gestao = Role::where('slug', 'gestao')->firstOrFail();

        $this->actingAs($operator)->patchJson("/api/auth/roles/{$gestao->id}/permissions", ['permissions' => [$key => true]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.permissions.0', 'rolesAndPermissions.operatorOnlyKey');

        expect($gestao->rolePermissions()->where('permission_key', $key)->where('value', true)->exists())->toBeFalse();
    })->with(['platform.firms', 'platform.consent']);
});
