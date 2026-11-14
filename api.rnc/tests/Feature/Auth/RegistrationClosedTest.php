<?php

/*
 * rnc-4 F1a — A5 (R7, D21). Self-registration is closed: an account is made by `firm:create`, the
 * platform console or the firm's manager, never by a stranger on a firm's host. The routes stay in
 * code behind `registration.enabled` (default false) and answer 404 — the same as a route that
 * never existed, so the door is not even advertised.
 */
it('answers 404 to the self-registration routes while registration is closed', function (string $uri, array $body) {
    $this->postJson($uri, $body)->assertNotFound();
})->with([
    'register' => ['/api/auth/register', ['email' => 'ana@example.test']],
    'email code' => ['/api/auth/email/code', ['email' => 'ana@example.test']],
    'email verify' => ['/api/auth/email/verify', ['email' => 'ana@example.test', 'code' => '123456']],
]);

it('is closed unless the environment opens it', function () {
    expect(config('registration.enabled'))->toBeFalse();
});

it('reaches the registration rules again once it is opened', function () {
    config(['registration.enabled' => true]);

    $this->postJson('/api/auth/register', [])->assertUnprocessable();
});
