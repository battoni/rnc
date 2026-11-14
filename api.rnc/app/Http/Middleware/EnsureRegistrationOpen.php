<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-registration answers 404 while `registration.enabled` is false (rnc-4 R7, D21).
 *
 * The routes stay registered and the door is shut here, not by leaving them out of the route file:
 * the route matrix keeps classifying them, and a test can open the door with a config change —
 * routes are read once at boot, a middleware on every request. A 404, not a 403: a closed door is
 * not advertised.
 */
class EnsureRegistrationOpen
{
    public const ALIAS = 'registration.open';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('registration.enabled'), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
