<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\MeUpdateDTO;
use App\Modules\Auth\Requests\UpdateMeRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\MeService;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __construct(
        private MeService $meService
    ) {}

    public function update(UpdateMeRequest $request): JsonResponse
    {
        $user = $this->meService->update($request->user(), MeUpdateDTO::from($request->validated()));

        return ApiResponse::success(data: new UserResource($user));
    }
}
