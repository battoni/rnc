<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\DTOs\StoreConsentVersionDTO;
use App\Modules\Lead\Requests\StoreConsentVersionRequest;
use App\Modules\Lead\Resources\ConsentVersionResource;
use App\Modules\Lead\Services\ConsentTextService;
use Illuminate\Http\JsonResponse;

/**
 * The firm's consent versions on its manager's settings (rnc-4 R33, `firm.settings`): the history and
 * a new text as version n+1, authored by whoever wrote it. Always the CURRENT firm's — the signed-in
 * person's; no id in the route.
 */
class ConsentVersionController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private ConsentTextService $consentTextService) {}

    public function index(): JsonResponse
    {
        $versions = $this->consentTextService->historyOfCurrentFirm();

        return $versions === null
            ? ApiResponse::notFound(message: self::NOT_FOUND)
            : ApiResponse::success(data: ConsentVersionResource::collection($versions));
    }

    public function store(StoreConsentVersionRequest $request): JsonResponse
    {
        $version = $this->consentTextService->createForCurrentFirm(StoreConsentVersionDTO::from($request)->text, $request->user()?->id);

        return ! $version
            ? ApiResponse::notFound(message: self::NOT_FOUND)
            : ApiResponse::created(data: new ConsentVersionResource($version));
    }
}
