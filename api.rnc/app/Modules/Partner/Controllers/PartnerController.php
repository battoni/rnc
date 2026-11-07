<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Partner\DTOs\PartnerDTO;
use App\Modules\Partner\DTOs\UpdatePartnerDTO;
use App\Modules\Partner\Requests\StorePartnerRequest;
use App\Modules\Partner\Requests\UpdatePartnerRequest;
use App\Modules\Partner\Resources\PartnerResource;
use App\Modules\Partner\Services\PartnerService;
use Illuminate\Http\JsonResponse;

/** The partner registry (rnc-1 R21). Another firm's partner is a 404, never a 403. */
class PartnerController extends Controller
{
    private const NOT_FOUND = 'partners.notFound';

    public function __construct(private PartnerService $partnerService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(data: PartnerResource::collection($this->partnerService->list()));
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success(data: $this->partnerService->options());
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        return ApiResponse::created(data: new PartnerResource($this->partnerService->create(PartnerDTO::from($request))));
    }

    public function update(UpdatePartnerRequest $request, int $id): JsonResponse
    {
        $partner = $this->partnerService->update($id, UpdatePartnerDTO::from($request));

        return ! $partner ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PartnerResource($partner));
    }
}
