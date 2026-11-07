<?php

namespace App\Modules\Diagnostic\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Diagnostic\DTOs\StartDiagnosticDTO;
use App\Modules\Diagnostic\DTOs\UpdateDiagnosticDTO;
use App\Modules\Diagnostic\Requests\LookupCnpjRequest;
use App\Modules\Diagnostic\Requests\StartDiagnosticRequest;
use App\Modules\Diagnostic\Requests\UpdateDiagnosticRequest;
use App\Modules\Diagnostic\Resources\PublicCnpjLookupResource;
use App\Modules\Diagnostic\Resources\PublicDiagnosticResultResource;
use App\Modules\Diagnostic\Resources\PublicSimulationResource;
use App\Modules\Diagnostic\Services\DiagnosticService;
use App\Modules\Lead\Resources\PublicLeadResource;
use Illuminate\Http\JsonResponse;

/**
 * The public diagnostic, by lead token (rnc-1 R9–R14, plan AM3). An unknown, expired or other
 * firm's token is the same calm 404 on every route.
 */
class PublicDiagnosticController extends Controller
{
    private const NOT_FOUND = 'diagnostic.notFound';

    public function __construct(private DiagnosticService $diagnosticService) {}

    public function store(StartDiagnosticRequest $request): JsonResponse
    {
        $lead = $this->diagnosticService->start(StartDiagnosticDTO::from($request));

        return ! $lead ? ApiResponse::notFound(message: 'firms.notFound') : ApiResponse::created(data: (new PublicLeadResource($lead))->withToken());
    }

    public function lookupCnpj(LookupCnpjRequest $request, string $token): JsonResponse
    {
        $lookup = $this->diagnosticService->lookupCnpj($token, (string) $request->validated('cnpj'));

        return ! $lookup ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PublicCnpjLookupResource($lookup));
    }

    public function update(UpdateDiagnosticRequest $request, string $token): JsonResponse
    {
        $lead = $this->diagnosticService->update($token, UpdateDiagnosticDTO::from($request));

        return ! $lead ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PublicLeadResource($lead));
    }

    public function result(string $token): JsonResponse
    {
        $result = $this->diagnosticService->result($token);

        return ! $result ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PublicDiagnosticResultResource($result));
    }

    public function simulation(string $token): JsonResponse
    {
        $simulation = $this->diagnosticService->simulation($token);

        return ! $simulation ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PublicSimulationResource($simulation));
    }
}
