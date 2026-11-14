<?php

use Illuminate\Support\Facades\DB;

/*
 * rnc-4 F7 (R35, A44): `GET api/health` is what the load balancer and the uptime check ask. It
 * answers 200 `{status: ok, db: ok}` with no session, no firm and no sign-in, on any host, and 503
 * when the database does not answer — never a 500 from a middleware that touched the database first
 * (the session store and the firm lookup both live in it in production).
 */

it('answers ok with the database up, to a guest, with no session cookie and no firm field', function () {
    $response = $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'db' => 'ok']]);

    expect($response->headers->getCookies())->toBeEmpty();
});

it('answers on a host whose firm does not exist, since it reads no firm', function () {
    $this->withHeader('X-Firm', 'nao-existe')->getJson('/api/health')->assertOk()->assertJsonPath('data.db', 'ok');
});

it('answers 503 when the database does not answer, before any middleware reaches it', function () {
    $working = config('database.default');
    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent-dir/health.sqlite', 'prefix' => ''],
        'database.default' => 'broken',
    ]);
    DB::purge('broken');

    try {
        $response = $this->getJson('/api/health');
    } finally {
        // Back before the assertions: the test's own transaction rolls back on the working connection.
        config(['database.default' => $working]);
    }

    $response->assertStatus(503)->assertExactJson(['data' => ['status' => 'down', 'db' => 'down']]);
});
