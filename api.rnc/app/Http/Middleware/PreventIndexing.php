<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every response as off-limits to search and AI crawlers.
 *
 * `public/robots.txt` asks crawlers not to fetch; this header tells the ones that fetched
 * anyway not to index, cache, or excerpt what they got. The distinction matters: a URL
 * that is merely disallowed in robots.txt can still be indexed from an inbound link,
 * because the crawler never reads the page to discover it is unwelcome. Only the header
 * (or a meta tag, which a JSON API cannot carry) removes it.
 */
class PreventIndexing
{
    private const DIRECTIVES = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', self::DIRECTIVES);

        return $response;
    }
}
