<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsurePlatformFirm;
use App\Http\Middleware\EnsurePlatformOperator;
use App\Http\Middleware\EnsureRegistrationOpen;
use App\Http\Middleware\EnsureUserPermission;
use App\Http\Middleware\PreventIndexing;
use App\Http\Middleware\ResolveCurrentBrand;
use App\Http\Middleware\ResolveCurrentFirm;
use App\Http\Middleware\ResolveCurrentPartner;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Every response, web and api, carries `X-Robots-Tag`.
         *
         * `public/robots.txt` asks crawlers not to fetch; this tells the ones that fetched
         * anyway not to index what they got. The two are not redundant: a URL merely disallowed
         * in robots.txt can still be indexed from an inbound link, because the crawler never
         * reads the page to discover it is unwelcome. Only the header removes it — a JSON API
         * cannot carry a meta tag.
         */
        $middleware->prepend(PreventIndexing::class);

        $middleware->api(prepend: [
            HandleCors::class,
            StartSession::class,
            SetLocale::class,
        ]);

        // Appended, so the session has started and the user resolves: a deactivated account is
        // signed out on its next request, not when its session happens to expire.
        $middleware->api(append: [
            EnsureAccountActive::class,
            // After the user resolves: a signed-in person's firm wins over any `X-Firm` header.
            ResolveCurrentFirm::class,
        ]);

        $middleware->web(append: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'permission' => EnsureUserPermission::class,
            // The portal's partner (rnc-2 D2, plan AM1): on `portal/*` routes only, never global.
            ResolveCurrentPartner::ALIAS => ResolveCurrentPartner::class,
            // The public site's sub-brand (rnc-3 R2, D2): on `public/*` routes only, never global.
            ResolveCurrentBrand::ALIAS => ResolveCurrentBrand::class,
            // Self-registration is closed by default (rnc-4 R7, D21): the three routes answer 404.
            EnsureRegistrationOpen::ALIAS => EnsureRegistrationOpen::class,
            // A module the firm has off answers 404 (rnc-4 R17): `module:<key>[,<key>…]`.
            EnsureModuleEnabled::ALIAS => EnsureModuleEnabled::class,
            // The platform console (rnc-4 R24): the firm-less `operador` only, after the permission.
            EnsurePlatformOperator::ALIAS => EnsurePlatformOperator::class,
            // The platform's own site (rnc-4 R29): the platform firm only, else 404 before validation.
            EnsurePlatformFirm::ALIAS => EnsurePlatformFirm::class,
        ]);

        /**
         * A guest is not redirected anywhere — there is nowhere to send them.
         *
         * Laravel installs `redirectGuestsTo(fn () => route('login'))` by default, and this app
         * has no `login` route: the sign-in screen belongs to the SPA. So the default callback
         * throws RouteNotFoundException from INSIDE the auth middleware, before the
         * AuthenticationException it was building even exists — which is why no exception
         * handler could rescue it. Returning null says "no redirect", and the handler then
         * answers with the 401 it always meant to, in JSON (see `withExceptions` below).
         */
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Everything under `api/` answers in JSON, whatever the caller asked for.
         *
         * Without this, a guest who reaches a protected route WITHOUT `Accept: application/json`
         * gets a 500 where the truth is a 401. The SPA sends the header on every call and never
         * sees it; an uptime check, a crawler, or somebody pasting a URL into the address bar
         * sees an API that looks broken while it is merely locked. It served a real error in
         * production once, in lifeshifter, before anyone noticed the shape of it.
         *
         * Scoped to `api/*` on purpose: `/` still serves the welcome view, and an HTML page is
         * entitled to an HTML error.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
