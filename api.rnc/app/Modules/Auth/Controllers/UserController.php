<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\CheckUsernameDTO;
use App\Modules\Auth\DTOs\UserStoreDTO;
use App\Modules\Auth\DTOs\UserUpdateDTO;
use App\Modules\Auth\Requests\CheckUsernameRequest;
use App\Modules\Auth\Requests\ListUsersRequest;
use App\Modules\Auth\Requests\StoreUserRequest;
use App\Modules\Auth\Requests\UpdateUserRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\Auth\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private const RESET_PASSWORD_PERMISSION = 'users.reset_password';

    public function __construct(
        private PermissionService $permissionService,
        private UserService $userService,
    ) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = $this->userService->list(
            $request->ids(),
            $request->orderBy(),
            $request->profileType(),
            Pagination::perPage($request)
        );

        return ApiResponse::paginated($users, UserResource::class);
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->find($id);

        return ! $user
            ? ApiResponse::notFound(message: 'User not found')
            : ApiResponse::success(data: new UserResource($user));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $dto = UserStoreDTO::from($request);
        $user = $this->userService->create($dto);

        return ApiResponse::created(data: new UserResource($user));
    }

    public function update(int $id, UpdateUserRequest $request): JsonResponse
    {
        // A permission that depends on the body: `users.update` edits an account, and only
        // `users.reset_password` may also replace its password. The screen hides the field without
        // it, and the API used to accept it anyway — an editor could take over any account.
        $isResettingPassword = $request->filled('password');
        $canResetPassword = in_array(self::RESET_PASSWORD_PERMISSION, $this->permissionService->forUser($request->user()), true);

        if ($isResettingPassword && ! $canResetPassword) {
            return ApiResponse::forbidden(message: 'users.form.passwordResetPermissionRequired');
        }

        $dto = UserUpdateDTO::from($request);
        $user = $this->userService->update($id, $dto, $request->user()?->id);

        return ! $user
            ? ApiResponse::notFound(message: 'User not found')
            : ApiResponse::success(data: new UserResource($user));
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $this->userService->find($id);

        if (! $user) {
            return ApiResponse::notFound(message: 'User not found');
        }

        $this->userService->delete($id, $request->user()?->id);

        return ApiResponse::success(data: new UserResource($user));
    }

    public function archive(int $id, Request $request): JsonResponse
    {
        $user = $this->userService->archive($id, $request->user()?->id);

        return ! $user
            ? ApiResponse::notFound(message: 'User not found')
            : ApiResponse::success(data: new UserResource($user));
    }

    public function checkUsername(CheckUsernameRequest $request): JsonResponse
    {
        $dto = CheckUsernameDTO::from($request);
        $available = $this->userService->isUsernameAvailable($dto->username, $dto->exclude_id);

        return $available
            ? ApiResponse::success(data: ['available' => true])
            : ApiResponse::conflict(message: 'users.form.usernameTaken');
    }
}
