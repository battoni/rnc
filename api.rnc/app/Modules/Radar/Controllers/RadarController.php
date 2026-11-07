<?php

namespace App\Modules\Radar\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Radar\Resources\RadarClientResource;
use App\Modules\Radar\Services\RadarService;
use Illuminate\Http\JsonResponse;

/**
 * "Radar da carteira" (rnc-2 R24–R26), behind `radar.manage` (ceo, operador). Read only: the batch
 * referral is F10's. An unknown rule is a 404.
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

    /** @param  array{type: array<string, mixed>, clients: list<array<string, mixed>>}  $type */
    private function typeResponse(array $type): JsonResponse
    {
        return ApiResponse::success(data: [
            'type' => $type['type'],
            'clients' => RadarClientResource::collection($type['clients']),
        ]);
    }
}
