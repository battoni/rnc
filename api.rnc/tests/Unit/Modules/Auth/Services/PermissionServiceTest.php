<?php

use App\Models\User;
use App\Modules\Auth\Helpers\EffectivePermissionsHelper;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Services\PermissionService;
use Tests\TestCase;

uses(TestCase::class);

function makePermissionService(
    RoleRepository $roleRepo,
    UserPermissionRepository $permRepo,
): PermissionService {
    return new PermissionService($roleRepo, $permRepo);
}

it('forUser returns role permissions merged with user overrides (user wins)', function () {
    $user = new User(['id' => 1]);
    $user->id = 1;
    $user->role_id = 10;

    $roleRepo = Mockery::mock(RoleRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);

    $roleRepo->shouldReceive('getRolePermissions')
        ->with(10)
        ->andReturn(['users.read' => true, 'users.delete' => true]);

    $permRepo->shouldReceive('getUserPermissions')
        ->with(1)
        ->andReturn(['users.delete' => false]); // user overrides role: revoke delete

    $service = makePermissionService($roleRepo, $permRepo);
    $result = $service->forUser($user);

    expect($result)->toContain('users.read')
        ->and($result)->not->toContain('users.delete');
});

it('forUser returns empty array when user has no role and no overrides', function () {
    $user = new User;
    $user->id = 2;
    $user->role_id = null;

    $roleRepo = Mockery::mock(RoleRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);

    $permRepo->shouldReceive('getUserPermissions')
        ->with(2)
        ->andReturn([]);

    $service = makePermissionService($roleRepo, $permRepo);
    $result = $service->forUser($user);

    expect($result)->toBeEmpty();
});

it('forUsers returns empty map when passed empty array', function () {
    $roleRepo = Mockery::mock(RoleRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);

    $service = makePermissionService($roleRepo, $permRepo);
    $result = $service->forUsers([]);

    expect($result)->toBeEmpty();
});

it('forUsers returns permissions indexed by user id', function () {
    $userA = new User;
    $userA->id = 1;
    $userA->role_id = 10;

    $userB = new User;
    $userB->id = 2;
    $userB->role_id = 10;

    $roleRepo = Mockery::mock(RoleRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);

    $roleRepo->shouldReceive('getRolePermissionsForRoles')
        ->with([10])
        ->andReturn([10 => ['users.read' => true]]);

    $permRepo->shouldReceive('getUserPermissionsForUsers')
        ->with([1, 2])
        ->andReturn([
            1 => [],
            2 => ['users.read' => false], // userB explicitly revoked
        ]);

    $service = makePermissionService($roleRepo, $permRepo);
    $result = $service->forUsers([$userA, $userB]);

    expect($result[1])->toContain('users.read')
        ->and($result[2])->not->toContain('users.read');
});

it('EffectivePermissionsHelper merges role and user permissions with user winning', function () {
    $rolePerms = ['users.read' => true, 'users.delete' => true, 'users.create' => false];
    $userPerms = ['users.delete' => false, 'users.create' => true];

    $result = EffectivePermissionsHelper::effectivePermissionKeys($rolePerms, $userPerms);

    expect($result)->toContain('users.read')
        ->and($result)->toContain('users.create')
        ->and($result)->not->toContain('users.delete');
});
