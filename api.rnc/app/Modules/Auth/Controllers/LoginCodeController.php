<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\LoginCodeRequestDTO;
use App\Modules\Auth\DTOs\LoginCodeVerifyDTO;
use App\Modules\Auth\Requests\RequestLoginCodeRequest;
use App\Modules\Auth\Requests\VerifyLoginCodeRequest;
use App\Modules\Auth\Resources\LoginCodeRequestedResource;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\LoginCodeService;
use Illuminate\Http\JsonResponse;

class LoginCodeController extends Controller
{
    public function __construct(
        private LoginCodeService $loginCodeService
    ) {}

    public function requestCode(RequestLoginCodeRequest $request): JsonResponse
    {
        $result = $this->loginCodeService->request(LoginCodeRequestDTO::from($request->validated()));

        return ApiResponse::success(data: new LoginCodeRequestedResource($result));
    }

    public function verify(VerifyLoginCodeRequest $request): JsonResponse
    {
        $user = $this->loginCodeService->verify(LoginCodeVerifyDTO::from($request->validated()), $request);

        return ApiResponse::success(data: new UserResource($user));
    }
}
