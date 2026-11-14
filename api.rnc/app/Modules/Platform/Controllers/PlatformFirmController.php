<?php

namespace App\Modules\Platform\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Firm\DTOs\CreateFirmDTO;
use App\Modules\Firm\DTOs\FirmManagerDTO;
use App\Modules\Firm\DTOs\UpdateFirmLookDTO;
use App\Modules\Firm\Models\Firm;
use App\Modules\Platform\Requests\StoreFirmManagerRequest;
use App\Modules\Platform\Requests\StorePlatformFirmRequest;
use App\Modules\Platform\Requests\UpdatePlatformFirmModulesRequest;
use App\Modules\Platform\Requests\UpdatePlatformFirmRequest;
use App\Modules\Platform\Resources\ManagerLoginResource;
use App\Modules\Platform\Resources\PlatformFirmResource;
use App\Modules\Platform\Resources\ProvisionedFirmResource;
use App\Modules\Platform\Services\PlatformFirmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The operator's console over the firms (rnc-4 R25): `platform.firms` + the firm-less operator
 * (`platform.operator`). The firm a route names by id is the one it touches — an unknown id is 404
 * `firms.notFound`; the request's own firm (the platform firm) plays no part.
 */
class PlatformFirmController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private PlatformFirmService $platformFirmService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(data: PlatformFirmResource::collection($this->platformFirmService->list()));
    }

    public function store(StorePlatformFirmRequest $request): JsonResponse
    {
        $provisioned = $this->platformFirmService->create(CreateFirmDTO::from($request), $this->operatorOf($request));

        return ApiResponse::created(data: new ProvisionedFirmResource($provisioned));
    }

    public function update(UpdatePlatformFirmRequest $request, int $id): JsonResponse
    {
        return $this->answer($this->platformFirmService->updateLook($id, UpdateFirmLookDTO::from($request)));
    }

    public function modules(UpdatePlatformFirmModulesRequest $request, int $id): JsonResponse
    {
        return $this->answer($this->platformFirmService->setModules($id, $request->modules(), $this->operatorOf($request)));
    }

    public function manager(StoreFirmManagerRequest $request, int $id): JsonResponse
    {
        $login = $this->platformFirmService->provideManager($id, FirmManagerDTO::from($request));

        if (! $login) {
            return ApiResponse::notFound(message: self::NOT_FOUND);
        }

        $resource = new ManagerLoginResource($login);

        return $login->created ? ApiResponse::created(data: $resource) : ApiResponse::success(data: $resource);
    }

    /** @param  array{firm: Firm, lead_count: int}|null  $firm */
    private function answer(?array $firm): JsonResponse
    {
        return ! $firm ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PlatformFirmResource($firm));
    }

    /** The route's middleware let only the operator through. */
    private function operatorOf(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
