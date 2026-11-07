<?php

use App\Http\Middleware\EnsureUserPermission;
use App\Http\Middleware\ResolveCurrentPartner;
use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * The permission matrix (rnc-2 A2, R3–R4; plan P10, AM2): every API route is classified in
 * `routeClasses.php`, and each class is walked as the people it must refuse.
 *
 * - a `parceiro` user — with its partner — gets 403 on every staff route;
 * - `equipe`, `ceo` and `operador`, none with a `partner_id`, get 403 on every portal route (the
 *   operator holds `portal.access` and is refused by `portal` for lacking a partner);
 * - a guest gets 401 on staff, portal and shared routes, and a partner reaches the shared ones;
 * - a route registered and not classified — or classified and gone — fails the first test.
 */
const MATRIX_PARAMETER_PLACEHOLDER = '1';

/** @return array<string, string> `METHOD uri` → class */
function matrixClasses(): array
{
    return require __DIR__.'/routeClasses.php';
}

/** @return array<string, array{0: string, 1: string}> dataset of [method, uri] for one class */
function matrixRoutesOf(string $class): array
{
    $routes = [];

    foreach (matrixClasses() as $key => $routeClass) {
        if ($routeClass !== $class) {
            continue;
        }

        [$method, $uri] = explode(' ', $key, 2);
        $routes[$key] = [$method, $uri];
    }

    return $routes;
}

/** @return array<string, RoutingRoute> every registered `api/` route by `METHOD uri` */
function matrixRegisteredRoutes(): array
{
    $registered = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $registered[$method.' '.Str::after($route->uri(), 'api/')] = $route;
        }
    }

    return $registered;
}

/** The uri with each parameter filled by a value its constraint accepts (`whereUuid` → a uuid). */
function matrixUrl(string $method, string $uri): string
{
    $route = matrixRegisteredRoutes()[$method.' '.$uri];

    $path = preg_replace_callback('/\{(\w+)\??}/', function (array $match) use ($route): string {
        $pattern = $route->wheres[$match[1]] ?? null;

        return $pattern !== null && preg_match('/^'.$pattern.'$/', MATRIX_PARAMETER_PLACEHOLDER) !== 1
            ? (string) Str::uuid()
            : MATRIX_PARAMETER_PLACEHOLDER;
    }, $uri);

    return '/api/'.$path;
}

/** @return list<string> the middleware names of a route, as `route:list` prints them */
function matrixMiddleware(string $method, string $uri): array
{
    return Route::gatherRouteMiddleware(matrixRegisteredRoutes()[$method.' '.$uri]);
}

function matrixPerson(string $role, ?int $partnerId = null): User
{
    return User::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'role_id' => Role::where('slug', $role)->value('id'),
        'partner_id' => $partnerId,
    ]);
}

function matrixPartnerUser(): User
{
    $partner = Partner::withoutGlobalScopes()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'name' => 'NexoERP (exemplo)',
        'specialty' => 'erp_nfe',
        'email' => 'contato3@parceiro.exemplo',
        'active' => true,
    ]);

    return matrixPerson('parceiro', $partner->id);
}

beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class]));

dataset('staff routes', fn () => matrixRoutesOf('staff'));
dataset('portal routes', fn () => matrixRoutesOf('portal'));
dataset('shared routes', fn () => matrixRoutesOf('shared'));
dataset('public routes', fn () => matrixRoutesOf('public'));
dataset('signed-in routes', fn () => [...matrixRoutesOf('staff'), ...matrixRoutesOf('portal'), ...matrixRoutesOf('shared')]);
dataset('staff without a partner', ['equipe', 'ceo', 'operador']);

describe('the classification (A2)', function () {
    it('lists every registered API route, and only those', function () {
        $registered = array_keys(matrixRegisteredRoutes());
        $classified = array_keys(matrixClasses());
        sort($registered);
        sort($classified);

        expect(array_values(array_diff($registered, $classified)))->toBe([], 'registered but not classified in routeClasses.php')
            ->and(array_values(array_diff($classified, $registered)))->toBe([], 'classified but not registered');
    });

    it('uses only the four classes', function () {
        expect(array_values(array_unique(matrixClasses())))->each->toBeIn(['public', 'portal', 'staff', 'shared']);
    });

    it('puts a staff route behind auth and a key the partner role never holds', function (string $method, string $uri) {
        $middleware = matrixMiddleware($method, $uri);
        $permission = collect($middleware)->first(fn (string $name) => str_starts_with($name, EnsureUserPermission::class.':'));

        expect($middleware)->toContain(Authenticate::class)
            ->and($permission)->not->toBeNull()
            ->and(explode(',', Str::after((string) $permission, ':')))->not->toContain('portal.access')
            ->and($middleware)->not->toContain('portal');
    })->with('staff routes');

    it('puts a portal route behind portal.access and the partner binding', function (string $method, string $uri) {
        expect(matrixMiddleware($method, $uri))
            ->toContain(Authenticate::class)
            ->toContain(EnsureUserPermission::class.':portal.access')
            ->toContain(ResolveCurrentPartner::class);
    })->with('portal routes');

    it('asks no session on a public route', function (string $method, string $uri) {
        expect(matrixMiddleware($method, $uri))->not->toContain(Authenticate::class);
    })->with('public routes');
});

describe('who each class refuses (A2)', function () {
    it('refuses a partner every staff route with 403', function (string $method, string $uri) {
        $this->actingAs(matrixPartnerUser())->json($method, matrixUrl($method, $uri))->assertForbidden();
    })->with('staff routes');

    it('refuses staff without a partner every portal route with 403', function (string $role, string $method, string $uri) {
        $this->actingAs(matrixPerson($role))->json($method, matrixUrl($method, $uri))->assertForbidden();
    })->with('staff without a partner')->with('portal routes');

    it('asks a guest to sign in on every staff, portal and shared route', function (string $method, string $uri) {
        $this->json($method, matrixUrl($method, $uri))->assertUnauthorized();
    })->with('signed-in routes');

    it('lets a partner reach the shared routes', function (string $method, string $uri) {
        $this->actingAs(matrixPartnerUser())->json($method, matrixUrl($method, $uri), ['name' => 'NexoERP'])->assertSuccessful();
    })->with('shared routes');

    it('lets a partner through the gate of every portal route', function (string $method, string $uri) {
        $status = $this->actingAs(matrixPartnerUser())->json($method, matrixUrl($method, $uri))->status();

        expect($status)->not->toBeIn([401, 403]);
    })->with('portal routes');
});
