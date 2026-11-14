<?php

use App\Models\User;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Auth\Services\UserService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The role repository is optional: most cases never reach it, and a permissive default keeps the
 * tests that do not care about roles from having to say so.
 */
function makeUserService(
    PermissionService $permService,
    UserPermissionRepository $permRepo,
    UserRepository $userRepo,
    ?RoleRepository $roleRepo = null,
): UserService {
    if (! $roleRepo) {
        $roleRepo = Mockery::mock(RoleRepository::class);
        $roleRepo->shouldReceive('getRolePermissions')->andReturn([]);
    }

    return new UserService(userServiceFirm(), $permService, $roleRepo, $permRepo, $userRepo);
}

const USER_SERVICE_FIRM_ID = 7;

/** The request's firm: every user read is scoped to it (rnc-4 AM2). */
function userServiceFirm(): CurrentFirm
{
    $firm = new Firm;
    $firm->id = USER_SERVICE_FIRM_ID;
    $currentFirm = new CurrentFirm;
    $currentFirm->set($firm);

    return $currentFirm;
}

it('find returns null when user does not exist', function () {
    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $userRepo->shouldReceive('findFirmUserById')->with(999, USER_SERVICE_FIRM_ID)->andReturn(null);

    $service = makeUserService($permService, $permRepo, $userRepo);

    expect($service->find(999))->toBeNull();
});

it('find returns user with permissions attribute when found', function () {
    $user = new User(['id' => 1]);
    $user->id = 1;

    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $userRepo->shouldReceive('findFirmUserById')->with(1, USER_SERVICE_FIRM_ID)->andReturn($user);
    $permService->shouldReceive('forUser')->with($user)->andReturn(['users.read', 'users.update']);

    $service = makeUserService($permService, $permRepo, $userRepo);
    $result = $service->find(1);

    expect($result)->not->toBeNull()
        ->and($result->getAttribute('permissions'))->toBe(['users.read', 'users.update']);
});

it('isUsernameAvailable returns true when username is not taken', function () {
    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $userRepo->shouldReceive('findUserByUsername')->with('newuser', null)->andReturn(null);

    $service = makeUserService($permService, $permRepo, $userRepo);

    expect($service->isUsernameAvailable('newuser'))->toBeTrue();
});

it('isUsernameAvailable returns false when username is already taken', function () {
    $existingUser = new User;

    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $userRepo->shouldReceive('findUserByUsername')->with('alice', null)->andReturn($existingUser);

    $service = makeUserService($permService, $permRepo, $userRepo);

    expect($service->isUsernameAvailable('alice'))->toBeFalse();
});

it('isUsernameAvailable excludes the given id (edit mode)', function () {
    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $userRepo->shouldReceive('findUserByUsername')->with('alice', 1)->andReturn(null);

    $service = makeUserService($permService, $permRepo, $userRepo);

    expect($service->isUsernameAvailable('alice', 1))->toBeTrue();
});

it('list returns users with permissions attribute attached', function () {
    $user = new User(['id' => 1]);
    $user->id = 1;

    $userRepo = Mockery::mock(UserRepository::class);
    $permRepo = Mockery::mock(UserPermissionRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $paginator = new LengthAwarePaginator([$user], total: 1, perPage: 15);

    $userRepo->shouldReceive('findAllUsers')->with(USER_SERVICE_FIRM_ID, null, null, null, 15)->andReturn($paginator);
    $permService->shouldReceive('forUsers')->with([$user])->andReturn([1 => ['users.read']]);

    $service = makeUserService($permService, $permRepo, $userRepo);
    $result = $service->list();

    expect($result->items()[0]->getAttribute('permissions'))->toBe(['users.read']);
});
