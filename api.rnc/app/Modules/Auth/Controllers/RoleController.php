<?php

namespace App\Modules\Auth\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Auth\DTOs\RolePermissionsUpdateDTO;
use App\Modules\Auth\DTOs\RoleStoreDTO;
use App\Modules\Auth\DTOs\RoleUpdateDTO;
use App\Modules\Auth\Requests\StoreRoleRequest;
use App\Modules\Auth\Requests\UpdateRolePermissionsRequest;
use App\Modules\Auth\Requests\UpdateRoleRequest;
use App\Modules\Auth\Resources\RoleResource;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->roleService->list();

        return ApiResponse::success(data: RoleResource::collection($roles));
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->roleService->find($id);

        return ! $role ?
            ApiResponse::notFound(message: 'Role not found') :
            ApiResponse::success(data: new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $dto = RoleStoreDTO::from($request);
        $role = $this->roleService->create($dto);

        return ApiResponse::created(data: new RoleResource($role));
    }

    public function update(int $id, UpdateRoleRequest $request): JsonResponse
    {
        $dto = RoleUpdateDTO::from($request);
        $role = $this->roleService->update($id, $dto);

        return ! $role
            ? ApiResponse::notFound(message: 'Role not found')
            : ApiResponse::success(data: new RoleResource($role));
    }

    public function updatePermissions(int $id, UpdateRolePermissionsRequest $request): JsonResponse
    {
        $dto = RolePermissionsUpdateDTO::from($request);
        $role = $this->roleService->updatePermissions($id, $dto);

        return ! $role
            ? ApiResponse::notFound(message: 'Role not found')
            : ApiResponse::success(data: new RoleResource($role));
    }

    public function destroy(int $id): JsonResponse
    {
        $role = $this->roleService->delete($id);

        return ! $role
            ? ApiResponse::notFound(message: 'Role not found')
            : ApiResponse::success(data: new RoleResource($role));
    }
}
