<?php

namespace App\Modules\Firm\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Resources\PublicPrivacyResource;
use App\Modules\Firm\Services\CurrentFirm;
use Illuminate\Http\JsonResponse;

/**
 * The privacy notice's data for this request's firm (rnc-4 F9a, R38). `ResolveCurrentFirm` already
 * chose the firm — an unknown or offboarded `X-Firm` never gets here (404) — so nothing is looked up.
 */
class PublicPrivacyController extends Controller
{
    public function __construct(private CurrentFirm $currentFirm) {}

    public function show(): JsonResponse
    {
        $firm = $this->currentFirm->get();

        return ! $firm ? ApiResponse::notFound(message: 'firms.notFound') : ApiResponse::success(data: new PublicPrivacyResource($firm));
    }
}
