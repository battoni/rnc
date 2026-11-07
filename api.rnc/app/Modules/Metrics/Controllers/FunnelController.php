<?php

namespace App\Modules\Metrics\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Metrics\DTOs\FunnelWindowDTO;
use App\Modules\Metrics\Requests\FunnelRequest;
use App\Modules\Metrics\Resources\FunnelResource;
use App\Modules\Metrics\Services\FunnelService;
use Illuminate\Http\JsonResponse;

/** Reinaldo's funnel (rnc-1 R24), behind `metrics.read` (AM8). */
class FunnelController extends Controller
{
    public function __construct(private FunnelService $funnelService) {}

    public function show(FunnelRequest $request): JsonResponse
    {
        $days = FunnelWindowDTO::from($request)->days ?? FunnelRequest::DEFAULT_DAYS;

        return ApiResponse::success(data: new FunnelResource($this->funnelService->funnel($days)));
    }
}
