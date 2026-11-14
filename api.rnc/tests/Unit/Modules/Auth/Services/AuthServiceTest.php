<?php

use App\Models\User;
use App\Modules\Auth\DTOs\LoginCredentialsDTO;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Firm\Services\FirmResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

function makeAuthService(
    PermissionService $permService,
    UserRepository $userRepo,
): AuthService {
    // The firm rule has its own tests (LoginFirmTest); here every account belongs to the request's firm.
    $currentFirm = new CurrentFirm;
    $currentFirm->set(new Firm);
    $firmResolver = Mockery::mock(FirmResolver::class);
    $firmResolver->shouldReceive('belongsTo')->andReturn(true);

    return new AuthService($currentFirm, $firmResolver, $permService, $userRepo);
}

it('authenticate throws ValidationException when username is not found', function () {
    $repo = Mockery::mock(UserRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $repo->shouldReceive('findUserByUsername')
        ->with('unknown')
        ->andReturn(null);

    $dto = LoginCredentialsDTO::from(['username' => 'unknown', 'password' => 'pass']);
    $request = Request::create('/api/auth/login', 'POST');

    $service = makeAuthService($permService, $repo);

    expect(fn () => $service->authenticate($dto, $request))
        ->toThrow(ValidationException::class);
});

it('authenticate throws ValidationException when password is incorrect', function () {
    $user = new User;
    $user->password = Hash::make('correct-password');

    $repo = Mockery::mock(UserRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $repo->shouldReceive('findUserByUsername')
        ->with('alice')
        ->andReturn($user);

    $dto = LoginCredentialsDTO::from(['username' => 'alice', 'password' => 'wrong-password']);
    $request = Request::create('/api/auth/login', 'POST');

    $service = makeAuthService($permService, $repo);

    expect(fn () => $service->authenticate($dto, $request))
        ->toThrow(ValidationException::class);
});

it('authenticate tells a deactivated account with the correct password to speak to an administrator', function () {
    $user = new User;
    $user->password = Hash::make('secret123');
    $user->status = UserStatus::INACTIVE;

    $repo = Mockery::mock(UserRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $repo->shouldReceive('findUserByUsername')
        ->with('carol')
        ->andReturn($user);

    $dto = LoginCredentialsDTO::from(['username' => 'carol', 'password' => 'secret123']);
    $request = Request::create('/api/auth/login', 'POST');

    $service = makeAuthService($permService, $repo);

    try {
        $service->authenticate($dto, $request);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors()['username'][0])->toBe('auth.accountInactive');
    }
});

it('getSupportNames returns empty array when there are no active users', function () {
    $repo = Mockery::mock(UserRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $repo->shouldReceive('findAllActiveUsers')->andReturn([]);

    $service = makeAuthService($permService, $repo);
    $result = $service->getSupportNames();

    expect($result)->toBeEmpty();
});

it('getSupportNames returns only names of users with reset_password permission', function () {
    $alice = new User;
    $alice->id = 1;
    $alice->name = 'Alice';
    $alice->last_name = 'Smith';

    $bob = new User;
    $bob->id = 2;
    $bob->name = 'Bob';
    $bob->last_name = 'Jones';

    $repo = Mockery::mock(UserRepository::class);
    $permService = Mockery::mock(PermissionService::class);

    $repo->shouldReceive('findAllActiveUsers')->andReturn([$alice, $bob]);
    $permService->shouldReceive('forUsers')
        ->andReturn([
            1 => ['users.reset_password'],
            2 => ['users.read'],
        ]);

    $service = makeAuthService($permService, $repo);
    $result = $service->getSupportNames();

    expect($result)->toBe(['Alice Smith'])
        ->and($result)->not->toContain('Bob Jones');
});
