<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * An `{id}` segment only ever matches digits.
 *
 * Found in chapaja (2026-07): `demands/available` sat beside `demands/{id}` and was never reached —
 * the parametric route, declared first, took "available" as an id and answered 404 for a resource
 * that did not exist. In api.rnc `routes/api.local.php` is required at the END of `routes/api.php`, so
 * a literal route a project adds there for a core resource (the `GET /{resource}/options` the
 * architecture rule asks for) lost to `auth/users/{id}` the same way, and `show(int $id)` then died with
 * a TypeError: a 500. `Route::pattern` in `AppServiceProvider` takes declaration order out of the
 * contract.
 */

describe('a numeric route parameter', function () {
    it('lets a literal segment declared after {id} be reached', function () {
        Route::get('api/auth/users/options', fn () => response()->json(['literal' => true]));

        $route = Route::getRoutes()->match(Request::create('/api/auth/users/options'));

        expect($route->uri())->toBe('api/auth/users/options');

        $this->getJson('/api/auth/users/options')->assertSuccessful()->assertJson(['literal' => true]);
    });

    it('answers 404, not a 500, to an id that is not a number', function () {
        $this->actingAs(userWithPermissions(['users.read']))->getJson('/api/auth/users/abc')->assertNotFound();
    });
});
