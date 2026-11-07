<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\get;

/*
 * The QA login-as door (`GET /api/qa/login-as/{username}`): the Chrome driver opens it instead of
 * typing into the login form, where the owner's 1Password pop-up breaks the extension. It signs
 * in WITHOUT a password, so the whole point of these tests is the refusal outside APP_ENV=local.
 */

beforeEach(fn () => app()->detectEnvironment(fn () => 'local'));

it('signs in as the user and hands the browser to the app', function () {
    $user = User::factory()->create(['username' => 'qa.demo']);

    get('/api/qa/login-as/qa.demo')
        ->assertRedirect(config('rnc.frontend_url').'/');

    $this->assertAuthenticatedAs($user);
});

it('lands on the path it was given inside the app', function () {
    User::factory()->create(['username' => 'qa.demo']);

    get('/api/qa/login-as/qa.demo?to=/settings')
        ->assertRedirect(config('rnc.frontend_url').'/settings');
});

it('never sends the browser to another host', function (string $to) {
    User::factory()->create(['username' => 'qa.demo']);

    get('/api/qa/login-as/qa.demo?to='.urlencode($to))
        ->assertRedirect(config('rnc.frontend_url').'/');
})->with(['//evil.test/x', 'https://evil.test/x', '/\\evil.test', 'settings']);

it('replaces the session of the user signed in before', function () {
    $before = User::factory()->create(['username' => 'manager.demo']);
    $after = User::factory()->create(['username' => 'qa.demo']);

    $this->actingAs($before);
    get('/api/qa/login-as/qa.demo');

    $this->assertAuthenticatedAs($after);
});

it('refuses an unknown or deactivated user', function () {
    User::factory()->create(['username' => 'inactive.demo', 'status' => UserStatus::INACTIVE]);

    get('/api/qa/login-as/nobody.demo')->assertNotFound();
    get('/api/qa/login-as/inactive.demo')->assertNotFound();

    $this->assertGuest();
});

it('refuses to sign anyone in outside local', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    User::factory()->create(['username' => 'qa.demo']);

    get('/api/qa/login-as/qa.demo')->assertNotFound();

    $this->assertGuest();
})->with(['production', 'staging', 'testing']);

it('starts a fresh session instead of reusing the round before', function () {
    User::factory()->create(['username' => 'qa.demo']);

    $this->withSession(['qa.leftover' => 'previous round'])
        ->get('/api/qa/login-as/qa.demo')
        ->assertSessionMissing('qa.leftover');
});

it('is not registered at all outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');
    Route::setRoutes(new RouteCollection);

    Route::prefix('api')->group(base_path('routes/api.php'));

    expect(fn () => Route::getRoutes()->match(Request::create('/api/qa/login-as/qa.demo', 'GET')))
        ->toThrow(NotFoundHttpException::class);
});
