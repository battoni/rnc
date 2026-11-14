<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\DTOs\BetaSignupDTO;
use App\Modules\Lead\Requests\BetaSignupRequest;
use App\Modules\Lead\Resources\PublicBetaSignupResource;
use App\Modules\Lead\Services\BetaSignupService;
use Illuminate\Http\JsonResponse;

/**
 * The root site's "Quero participar do beta" (rnc-4 R29): the signup and the consent words it asks
 * under. The platform firm only — any other firm is 404 (`platform.firm`, then the service).
 */
class PublicBetaSignupController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private BetaSignupService $betaSignupService) {}

    public function store(BetaSignupRequest $request): JsonResponse
    {
        $lead = $this->betaSignupService->signUp(BetaSignupDTO::from($request));

        return ! $lead ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new PublicBetaSignupResource($lead));
    }

    public function consent(): JsonResponse
    {
        $consent = $this->betaSignupService->consent();

        return ! $consent ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: $consent);
    }
}
