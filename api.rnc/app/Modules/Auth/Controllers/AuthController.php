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
use App\Modules\Firm\Services\ViewAsFirm;
use App\Modules\Platform\Enums\ViewAsEndReason;
use App\Modules\Platform\Services\ViewAsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PermissionService $permissionService,
        private ViewAsFirm $viewAsFirm,
        private ViewAsService $viewAsService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        // A sign-in over a live view-as ends it first, logged as `logout` (verify P2-A4): the session
        // that held it is being replaced, and the firm of this login is the host's.
        $this->viewAsService->end($request, ViewAsEndReason::Logout);
        $credentials = LoginCredentialsDTO::from($request);
        $user = $this->authService->authenticate($credentials, $request);

        // rnc-4 F3c: the same `firm` and `platform` as `auth/me`, so the shell's rail and router know
        // the firm's modules from the first screen after sign-in. A sign-in never carries a view-as: it
        // closed any left open (`CloseViewAsOnSignIn`, rnc-4 D20).
        return ApiResponse::success(data: (new UserResource($user))->withPlatform());
    }

    public function logout(Request $request): JsonResponse
    {
        // A view-as ends with the session that held it, logged as `logout` (rnc-4 R26, D20).
        $this->viewAsService->end($request, ViewAsEndReason::Logout);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success('Logged out');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->setAttribute('permissions', $this->permissionService->forUser($user));

        return ApiResponse::success(data: (new UserResource($user))->withPlatform($this->viewAsFirm->resolve($request)));
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
