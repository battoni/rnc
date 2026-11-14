<?php

use Database\Seeders\DatabaseSeeder;

/*
 * A2 (rnc-1), API side: every seeded role signs in with the password flow (D9) and `auth/me` says
 * who they are, which firm they belong to and what they may open — the SPA's rail is drawn from
 * exactly these keys. The 403s of the trail, the metrics and the repricing are proven in the phase
 * that adds each endpoint (plan AM8).
 */
beforeEach(function () {
    config(['seeding.password' => 'local-secret', 'seeding.superadmin_password' => 'operator-secret']);

    $this->seed(DatabaseSeeder::class);
});

// The operator `battoni` has no firm since rnc-4 F4a and signs in at the root only — its row moved
// to `tests/Feature/Platform/OperatorSignInTest` (A26).
dataset('seeded people', [
    'gestao' => ['reinaldo', 'local-secret', 'gestao', false],
    'equipe' => ['patricia', 'local-secret', 'equipe', false],
    'specialist' => ['mariana.toledo', 'local-secret', 'equipe', true],
]);

it('signs in and reports role, firm and specialist flag', function (string $username, string $password, string $role, bool $isSpecialist) {
    $this->postJson('/api/auth/login', ['username' => $username, 'password' => $password])->assertOk();

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.username', $username)
        ->assertJsonPath('data.role_slug', $role)
        ->assertJsonPath('data.firm_slug', 'franco')
        ->assertJsonPath('data.is_specialist', $isSpecialist);
})->with('seeded people');

it('hands the team no key of the governance, metrics or repricing screens', function () {
    $this->postJson('/api/auth/login', ['username' => 'patricia', 'password' => 'local-secret'])->assertOk();

    $permissions = $this->getJson('/api/auth/me')->json('data.permissions');

    expect($permissions)->toContain('leads.read')
        ->and($permissions)->not->toContain('metrics.read')
        ->and($permissions)->not->toContain('referrals.read')
        ->and($permissions)->not->toContain('repricing.manage');
});

it('refuses a wrong password', function () {
    $this->postJson('/api/auth/login', ['username' => 'reinaldo', 'password' => 'errada'])->assertStatus(422);
});
