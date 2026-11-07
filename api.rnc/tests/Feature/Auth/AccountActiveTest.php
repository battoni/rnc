<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;

/*
 * Deactivating an account has to take its access away NOW, not when its session expires. Login
 * already refused an inactive account; a session opened before the change is what these cover.
 */

it('signs a deactivated account out on its next request, with a 401', function () {
    $user = User::factory()->create(['status' => UserStatus::INACTIVE]);

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'auth.accountInactive');

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('lets an active account through untouched', function () {
    $user = User::factory()->create(['status' => UserStatus::ACTIVE]);

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertOk();
});

it('leaves a guest to the route, which answers its own 401', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();

    $this->getJson('/api/auth/support-names')->assertOk();
});
