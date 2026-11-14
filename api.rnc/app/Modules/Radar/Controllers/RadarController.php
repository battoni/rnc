<?php

namespace App\Modules\Radar\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Radar\DTOs\ReferRadarBatchDTO;
use App\Modules\Radar\Requests\ReferRadarBatchRequest;
use App\Modules\Radar\Resources\RadarClientResource;
use App\Modules\Radar\Services\RadarService;
use Illuminate\Http\JsonResponse;

/**
 * "Radar da carteira" (rnc-2 R24–R27), behind `radar.manage` (gestao, operador): the summary, one
 * type's clients and the batch referral of a row to one partner (F10). An unknown rule is a 404 on
 * the reads and a 422 on the batch.
 */
class RadarController extends Controller
{
    private const NOT_FOUND = 'radar.notFound';

    public function __construct(private RadarService $radarService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(data: $this->radarService->summary());
    }

    public function type(string $rule): JsonResponse
    {
        $known = DiagnosticRule::tryFrom($rule);

        return $known === null ? ApiResponse::notFound(message: self::NOT_FOUND) : $this->typeResponse($this->radarService->type($known));
    }

    /** `{sent, skipped_no_consent, skipped_already}` — 200 even when nothing was left to send. */
    public function refer(ReferRadarBatchRequest $request): JsonResponse
    {
        /** @var User $person */
        $person = $request->user();
        $batch = ReferRadarBatchDTO::from($request->validated());

        return ApiResponse::success(data: $this->radarService->referBatch($batch->rule, $batch->partner_id, $person));
    }

    /** @param  array{type: array<string, mixed>, clients: list<array<string, mixed>>}  $type */
    private function typeResponse(array $type): JsonResponse
    {
        return ApiResponse::success(data: [
            'type' => $type['type'],
            'clients' => RadarClientResource::collection($type['clients']),
        ]);
    }
}
