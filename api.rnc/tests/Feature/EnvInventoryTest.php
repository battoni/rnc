<?php

/*
 * rnc-4 F7 (R34, R36, A45): the deploy runbook names every key the API's config reads through
 * `env()` and every `VITE_*` key of the SPA's `.env.example`, so a server is never built from a
 * list that misses one. A key added to a config file without a runbook line fails here by name.
 * Its "Database" section says how the suite ran on MySQL 8 — the Pest count, or why it did not.
 */

const ENV_INVENTORY_RUNBOOK = __DIR__.'/../../../docs/deploy/runbook.md';

/** @return list<string> every key `config/*.php` reads with `env('KEY')`, `env("KEY")` or `env(key: 'KEY')` */
function envInventoryApiKeys(): array
{
    $keys = [];

    foreach (glob(config_path('*.php')) as $file) {
        preg_match_all('/env\(\s*(?:key:\s*)?[\'"]([A-Z0-9_]+)[\'"]/', (string) file_get_contents($file), $matches);
        $keys = [...$keys, ...$matches[1]];
    }

    return array_values(array_unique($keys));
}

/** @return list<string> every `VITE_*` key `app.rnc/.env.example` sets */
function envInventorySpaKeys(): array
{
    preg_match_all('/^(VITE_[A-Z0-9_]+)=/m', (string) file_get_contents(base_path('../app.rnc/.env.example')), $matches);

    return array_values(array_unique($matches[1]));
}

function envInventoryRunbook(): string
{
    expect(ENV_INVENTORY_RUNBOOK)->toBeFile();

    return (string) file_get_contents(ENV_INVENTORY_RUNBOOK);
}

it('finds the keys it inventories (the scan itself works)', function () {
    expect(envInventoryApiKeys())->toContain('APP_KEY', 'DB_CONNECTION', 'PLATFORM_ROOT_DOMAIN', 'SANCTUM_STATEFUL_DOMAINS', 'TRUSTED_PROXIES', 'CORS_ALLOWED_ORIGINS')
        ->and(envInventorySpaKeys())->toContain('VITE_API_URL', 'VITE_ROOT_DOMAIN');
});

it('names every env key the API config reads', function () {
    $runbook = envInventoryRunbook();
    $missing = array_values(array_filter(envInventoryApiKeys(), fn (string $key) => ! str_contains($runbook, "`{$key}`")));

    expect($missing)->toBe([]);
});

it('names every VITE key of the SPA', function () {
    $runbook = envInventoryRunbook();
    $missing = array_values(array_filter(envInventorySpaKeys(), fn (string $key) => ! str_contains($runbook, "`{$key}`")));

    expect($missing)->toBe([]);
});

it('states in its Database section how the suite ran on MySQL 8', function () {
    expect(preg_match('/^## Database\n(.*?)(?=^## |\z)/ms', envInventoryRunbook(), $section))->toBe(1);

    expect($section[1])->toContain('MySQL 8')
        ->and(preg_match('/\d+ Pest|did not run/', $section[1]))->toBe(1);
});

it('says the folded migrations end at the first deploy', function () {
    expect(envInventoryRunbook())->toContain('folded migrations end at the first deploy')->toContain('add_');
});
