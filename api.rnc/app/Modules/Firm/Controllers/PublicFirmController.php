<?php

namespace App\Modules\Firm\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Resources\PublicFirmResource;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Http\JsonResponse;

/**
 * The firm of this request, for the public site's header and footer. `ResolveCurrentFirm` already
 * chose it (the `X-Firm` header or the default slug), so there is nothing left to look up.
 */
class PublicFirmController extends Controller
{
    public function __construct(private CurrentFirm $currentFirm) {}

    public function show(): JsonResponse
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? ApiResponse::notFound(message: 'firms.notFound') : ApiResponse::success(data: new PublicFirmResource($firm));
    }
}
