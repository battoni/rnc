<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The request's locale, from `Accept-Language`.
 *
 * The API answers in i18n keys, but a few strings leave it already written — a verification
 * e-mail's subject and body, sent inline while the request is open. Without this they came out in
 * the server's default whatever language the person was using. The SPA sends the header on every
 * request; a caller that sends none, or a language this app has no `lang/` directory for, keeps
 * the configured locale, so headless callers and the test suite behave as before.
 */
class SetLocale
{
    /** @var array<string, string> primary language tag, lowercased => `lang/` directory */
    private const SUPPORTED = [
        'en' => 'en',
        'en-us' => 'en',
        'pt' => 'pt_BR',
        'pt-br' => 'pt_BR',
        'pt_br' => 'pt_BR',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        if (! is_string($header) || $header === '') {
            return $next($request);
        }

        $primaryTag = strtolower(trim(explode(';', explode(',', $header)[0])[0]));

        if (isset(self::SUPPORTED[$primaryTag])) {
            app()->setLocale(self::SUPPORTED[$primaryTag]);
        }

        return $next($request);
    }
}
