<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Partner\DTOs\CreatePartnerAccessDTO;
use App\Modules\Partner\Requests\CreatePartnerAccessRequest;
use App\Modules\Partner\Resources\PartnerAccessResource;
use App\Modules\Partner\Services\PartnerAccessService;
use Illuminate\Http\JsonResponse;

/**
 * A partner's portal login (rnc-2 R2), behind `partners.manage`. Another firm's partner is a 404;
 * the password is in the answer of create and regenerate only.
 */
class PartnerAccessController extends Controller
{
    private const NOT_FOUND = 'partners.notFound';

    public function __construct(private PartnerAccessService $partnerAccessService) {}

    public function store(CreatePartnerAccessRequest $request, int $id): JsonResponse
    {
        $issued = $this->partnerAccessService->grant($id, CreatePartnerAccessDTO::from($request));

        return ! $issued
            ? ApiResponse::notFound(message: self::NOT_FOUND)
            : ApiResponse::created(data: (new PartnerAccessResource($issued->partner))->withPassword($issued->password));
    }

    public function regeneratePassword(int $id): JsonResponse
    {
        $issued = $this->partnerAccessService->regeneratePassword($id);

        return ! $issued
            ? ApiResponse::notFound(message: self::NOT_FOUND)
            : ApiResponse::success(data: (new PartnerAccessResource($issued->partner))->withPassword($issued->password));
    }

    public function destroy(int $id): JsonResponse
    {
        $partner = $this->partnerAccessService->revoke($id);

        return ! $partner ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PartnerAccessResource($partner));
    }
}
