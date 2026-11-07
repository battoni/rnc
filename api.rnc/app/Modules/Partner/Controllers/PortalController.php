<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Partner\Resources\PortalMeResource;
use App\Modules\Partner\Services\CurrentPartner;
use Illuminate\Http\JsonResponse;

/**
 * The partner's portal (rnc-2 R3–R4). Every route is `permission:portal.access` + `portal`, so
 * `CurrentPartner` is always set here; nothing reads a partner id from the request.
 */
class PortalController extends Controller
{
    public function __construct(private CurrentPartner $currentPartner) {}

    public function me(): JsonResponse
    {
        return ApiResponse::success(data: new PortalMeResource($this->currentPartner->get()));
    }
}
