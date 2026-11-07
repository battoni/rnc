<?php

namespace App\Modules\Briefing\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Briefing\Resources\BriefingResource;
use App\Modules\Briefing\Services\BriefingService;
use Illuminate\Http\JsonResponse;

/**
 * Reinaldo's briefing (rnc-3 R23): one read behind `briefing.read` (ceo, operador), under the
 * signed-in person's firm. It writes nothing.
 */
class BriefingController extends Controller
{
    public function __construct(private BriefingService $briefingService) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(data: new BriefingResource($this->briefingService->build()));
    }
}
