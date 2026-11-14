<?php

use App\Models\User;
use Database\Seeders\FirmSeeder;

/*
 * rnc-4 F1a — plan AM4 (R6, D4). The SPA on `franco.localhost:5173` reaches the API through Vite's
 * `/api` proxy, so the browser sends `Origin: http://franco.localhost:5173` and keeps a host-only
 * session cookie for that host. The session the login opened must carry the next request.
 *
 * The API's session does not go through Sanctum's stateful middleware (the api group starts the
 * session itself and the guard is `web`), so the stateful list gates nothing and an unlisted origin
 * is not a 401 (state.md W4). What keeps another site out is CORS: no `Access-Control-Allow-Origin`
 * for an origin that is not ours, so its page never reads the answer.
 */
beforeEach(function () {
    $this->seed(FirmSeeder::class);
    User::factory()->create(['username' => 'ana', 'password' => 'segredo-forte']);
});

it('keeps the session a login on a firm host opened', function () {
    $headers = ['Origin' => 'http://franco.localhost:5173', 'X-Firm' => 'franco'];

    $this->withHeaders($headers)
        ->postJson('/api/auth/login', ['username' => 'ana', 'password' => 'segredo-forte'])
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'ana');
});

it('lists the root and every firm host of the local root as stateful', function () {
    expect(config('sanctum.stateful'))->toContain('localhost:5173', '*.localhost:5173');
});

it('grants no CORS credentials to an origin that is not ours', function () {
    $response = $this->withHeaders(['Origin' => 'http://evil.test'])
        ->getJson('/api/public/firm')
        ->assertOk();

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://evil.test');
});
