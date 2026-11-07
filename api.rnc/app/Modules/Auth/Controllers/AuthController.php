<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\LoginCredentialsDTO;
use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PermissionService $permissionService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = LoginCredentialsDTO::from($request);
        $user = $this->authService->authenticate($credentials, $request);

        return ApiResponse::success(data: new UserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success('Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return ApiResponse::success(data: new UserResource($user));
    }

    public function permissionGroups(): JsonResponse
    {
        $structure = PermissionGroupsHelper::getPermissionGroupsStructure();

        return ApiResponse::success(data: $structure);
    }

    public function supportNames(): JsonResponse
    {
        return ApiResponse::success(data: $this->authService->getSupportNames());
    }
}
