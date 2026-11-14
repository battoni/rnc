<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\PlatformFirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * rnc-4 (plan P4, P6): the white-label platform's routes, phase by phase — who is refused (a
 * partner, the team, a firm's manager: 403; a guest: 401). The route matrix (`RouteMatrixTest`)
 * sweeps every staff route for the partner; this file names rnc-4's rows on purpose, so a key
 * granted too widely in a later phase fails here by name.
 *
 * F4a: the operator's console (`platform.firms`, `platform.consent`) — the `operador` role with no
 * firm, and nobody else: not a firm account handed the key in its own permissions, and no role but
 * the operator's can be given it (`RoleService`).
 *
 * F4c: the logo upload and view-as join the same rows (`platform.firms` + the firm-less operator).
 *
 * F7 (A46, R37): the firm's settings (F5a) and the platform site (F6a) join, and a last test finds
 * every route carrying an rnc-4 lock and fails when this file does not sweep it.
 *
 * F9a: a lead's export and anonymisation (`leads.update` + `firm.settings`) join.
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
    // F4c — the logo and view-as (R26, R27)
    'POST platform/firms/{id}/logo' => ['POST', '/api/platform/firms/{id}/logo'],
    'DELETE platform/firms/{id}/logo' => ['DELETE', '/api/platform/firms/{id}/logo'],
    'POST platform/firms/{id}/view-as' => ['POST', '/api/platform/firms/{id}/view-as'],
    'DELETE platform/view-as' => ['DELETE', '/api/platform/view-as'],
    'GET platform/view-as-log' => ['GET', '/api/platform/view-as-log'],
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

/** F5a the firm's settings (`firm.settings`, the manager; R30–R33). */
dataset('phase 4 firm settings routes', [
    'GET firm/settings' => ['GET', '/api/firm/settings'],
    'PATCH firm/settings' => ['PATCH', '/api/firm/settings'],
    'GET firm/consent-versions' => ['GET', '/api/firm/consent-versions'],
    'POST firm/consent-versions' => ['POST', '/api/firm/consent-versions'],
    'GET specialists' => ['GET', '/api/specialists'],
    'GET specialists/{id}/hours' => ['GET', '/api/specialists/{id}/hours'],
    'PUT specialists/{id}/hours' => ['PUT', '/api/specialists/{id}/hours'],
]);

/** F9a a lead's own data (`leads.update` + the manager's `firm.settings`; R39). */
dataset('phase 4 lead privacy routes', [
    'GET leads/{id}/export' => ['GET', '/api/leads/{id}/export'],
    'POST leads/{id}/anonymise' => ['POST', '/api/leads/{id}/anonymise'],
]);

/** F6a the platform's own site (`platform.firm`: the platform firm only, R29). */
dataset('phase 4 platform site routes', [
    'GET public/beta-signups/consent' => ['GET', '/api/public/beta-signups/consent'],
    'POST public/beta-signups' => ['POST', '/api/public/beta-signups'],
]);

/** The rnc-4 routes this file sweeps, by `METHOD uri` (A46); `GET health` is `HealthTest`'s. */
const PHASE4_SWEPT_ROUTES = [
    'GET platform/firms', 'POST platform/firms', 'PATCH platform/firms/{id}', 'PUT platform/firms/{id}/modules',
    'POST platform/firms/{id}/manager', 'GET platform/consent-default', 'PUT platform/consent-default',
    'POST platform/firms/{id}/logo', 'DELETE platform/firms/{id}/logo', 'POST platform/firms/{id}/view-as',
    'DELETE platform/view-as', 'GET platform/view-as-log',
    'GET firm/settings', 'PATCH firm/settings', 'GET firm/consent-versions', 'POST firm/consent-versions',
    'GET specialists', 'GET specialists/{id}/hours', 'PUT specialists/{id}/hours',
    'GET public/beta-signups/consent', 'POST public/beta-signups',
    'GET health',
    'GET leads/{id}/export', 'POST leads/{id}/anonymise',
];

/** The locks rnc-4 introduced: a route carrying one is an rnc-4 route and must be swept here. */
const PHASE4_ROUTE_MARKERS = ['permission:platform.firms', 'permission:platform.consent', 'permission:firm.settings', 'platform.firm'];

describe('F5a firm settings', function () {
    it('refuses the team and a partner with 403, and a guest with 401', function (string $method, string $uri) {
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id,
            'name' => 'Andrade Advocacia (exemplo)',
            'specialty' => 'advocacia_tributaria',
            'email' => 'contato@andrade.exemplo',
            'active' => true,
        ]);
        $url = phase4Url($uri);

        $this->actingAs(phase4Person('equipe', $this->franco->id))->json($method, $url)->assertForbidden();
        $this->actingAs(phase4Person('parceiro', $this->franco->id, $partner->id))->json($method, $url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->json($method, $url)->assertUnauthorized();
    })->with('phase 4 firm settings routes');

    it("answers 404 for another firm's specialist", function (string $method) {
        $alvorada = Firm::factory()->create(['slug' => 'alvorada']);
        $francoSpecialist = User::factory()->create(['firm_id' => $this->franco->id, 'is_specialist' => true]);

        $this->actingAs(phase4Person('gestao', $alvorada->id))
            ->json($method, "/api/specialists/{$francoSpecialist->id}/hours", ['hours' => []])
            ->assertNotFound();
    })->with(['GET', 'PUT']);

    it("refuses another firm's header from the manager with 403", function (string $method, string $uri) {
        Firm::factory()->create(['slug' => 'alvorada']);

        $this->actingAs(phase4Person('gestao', $this->franco->id))
            ->withHeader('X-Firm', 'alvorada')
            ->json($method, phase4Url($uri))
            ->assertForbidden();
    })->with('phase 4 firm settings routes');
});

describe('F6a the platform site', function () {
    it("answers 404 on a firm's host — the platform firm's alone", function (string $method, string $uri) {
        $this->withHeader('X-Firm', FirmSeeder::FRANCO_SLUG)->json($method, $uri)->assertNotFound();
    })->with('phase 4 platform site routes');
});

describe('F9a lead privacy', function () {
    it('refuses the team and a partner with 403, a guest with 401, and answers 404 for another firm\'s lead', function (string $method, string $uri) {
        $alvorada = Firm::factory()->create(['slug' => 'alvorada']);
        $lead = Lead::factory()->create(['firm_id' => $this->franco->id]);
        $partner = Partner::withoutGlobalScopes()->create([
            'firm_id' => $this->franco->id,
            'name' => 'Andrade Advocacia (exemplo)',
            'specialty' => 'advocacia_tributaria',
            'email' => 'contato@andrade.exemplo',
            'active' => true,
        ]);
        $url = str_replace('{id}', (string) $lead->id, $uri);

        $this->actingAs(phase4Person('equipe', $this->franco->id))->json($method, $url)->assertForbidden();
        $this->actingAs(phase4Person('parceiro', $this->franco->id, $partner->id))->json($method, $url)->assertForbidden();
        $this->actingAs(phase4Person('gestao', $alvorada->id))->json($method, $url)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->json($method, $url)->assertUnauthorized();
    })->with('phase 4 lead privacy routes');
});

describe('A46 every rnc-4 route is swept', function () {
    it('finds every route carrying an rnc-4 lock here, and each swept route is registered and classified', function () {
        $registered = [];
        $marked = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.Str::after($route->uri(), 'api/');
                $registered[] = $key;

                if (array_intersect(PHASE4_ROUTE_MARKERS, (array) $route->middleware()) !== []) {
                    $marked[] = $key;
                }
            }
        }

        $classes = require __DIR__.'/routeClasses.php';

        expect(array_values(array_diff($marked, PHASE4_SWEPT_ROUTES)))->toBe([])
            ->and(array_values(array_diff(PHASE4_SWEPT_ROUTES, $registered)))->toBe([])
            ->and(array_values(array_diff(PHASE4_SWEPT_ROUTES, array_keys($classes))))->toBe([]);
    });
});
