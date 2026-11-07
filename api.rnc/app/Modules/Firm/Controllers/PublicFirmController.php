<?php

namespace App\Modules\Firm\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Resources\PublicFirmResource;
use App\Modules\Firm\Services\CurrentBrand;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Http\JsonResponse;

/**
 * The firm of this request, for the public site's header and footer. `ResolveCurrentFirm` already
 * chose it (the `X-Firm` header or the default slug), and `ResolveCurrentBrand` its sub-brand (the
 * `X-Brand` header, rnc-3 R2), so there is nothing left to look up.
 */
class PublicFirmController extends Controller
{
    public function __construct(
        private CurrentFirm $currentFirm,
        private CurrentBrand $currentBrand,
    ) {}

    public function show(): JsonResponse
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? ApiResponse::notFound(message: 'firms.notFound') : ApiResponse::success(data: (new PublicFirmResource($firm))->underBrand($this->currentBrand->get()));
    }
}
