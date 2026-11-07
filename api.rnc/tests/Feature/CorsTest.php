<?php

/*
 * The origins the SPA is really served from get CORS headers.
 *
 * Found in lifeshifter (2026-09): `SANCTUM_STATEFUL_DOMAINS`, app.rnc's Playwright config and the
 * docs all serve the SPA from `http://localhost:5173`, but the CORS default listed only
 * `app.rnc.test`. A developer who opened localhost:5173 got a blank screen — the browser
 * dropped every API answer that came back without `Access-Control-Allow-Origin`.
 */

const CORS_DEV_ORIGIN = 'http://localhost:5173';

/**
 * The origin list `config/cors.php` produces when the environment does not set one.
 */
function corsDefaultAllowedOrigins(): array
{
    $saved = [$_ENV['CORS_ALLOWED_ORIGINS'] ?? null, $_SERVER['CORS_ALLOWED_ORIGINS'] ?? null, getenv('CORS_ALLOWED_ORIGINS')];

    unset($_ENV['CORS_ALLOWED_ORIGINS'], $_SERVER['CORS_ALLOWED_ORIGINS']);
    putenv('CORS_ALLOWED_ORIGINS');

    try {
        return (require config_path('cors.php'))['allowed_origins'];
    } finally {
        [$env, $server, $process] = $saved;

        if ($env !== null) {
            $_ENV['CORS_ALLOWED_ORIGINS'] = $env;
        }

        if ($server !== null) {
            $_SERVER['CORS_ALLOWED_ORIGINS'] = $server;
        }

        if ($process !== false) {
            putenv('CORS_ALLOWED_ORIGINS='.$process);
        }
    }
}

describe('the local dev origin', function () {
    it('is allowed by the default origin list', function () {
        expect(corsDefaultAllowedOrigins())->toContain(CORS_DEV_ORIGIN);
    });

    it('is listed in .env.example, which is what a fresh checkout copies', function () {
        $line = collect(file(base_path('.env.example'), FILE_IGNORE_NEW_LINES))
            ->first(fn (string $entry) => str_starts_with($entry, 'CORS_ALLOWED_ORIGINS='));

        expect(explode(',', substr($line, strlen('CORS_ALLOWED_ORIGINS='))))->toContain(CORS_DEV_ORIGIN);
    });

    it('receives the CORS headers on a preflight with the default list', function () {
        config(['cors.allowed_origins' => corsDefaultAllowedOrigins()]);

        $this->withHeaders([
            'Origin' => CORS_DEV_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/auth/login')
            ->assertHeader('Access-Control-Allow-Origin', CORS_DEV_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    });

    it('is not a wildcard: an unknown origin gets no allow header', function () {
        config(['cors.allowed_origins' => corsDefaultAllowedOrigins()]);

        $this->withHeaders([
            'Origin' => 'http://localhost:6666',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/auth/login')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    });
});
