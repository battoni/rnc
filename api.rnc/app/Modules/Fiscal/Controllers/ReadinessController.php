<?php

namespace App\Modules\Fiscal\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Fiscal\DTOs\ReadinessFilterDTO;
use App\Modules\Fiscal\Requests\ReadinessIndexRequest;
use App\Modules\Fiscal\Resources\ReadinessClientResource;
use App\Modules\Fiscal\Resources\ReadinessDetailResource;
use App\Modules\Fiscal\Services\ReadinessService;
use App\Modules\Portfolio\Resources\ClientEventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Prontidão da NF-e" (rnc-2 R18–R20), behind `fiscal.read` (equipe, ceo) — the notice too, on
 * purpose (plan AM13, Fiscal RULES.md). Another firm's client, an unknown id and a Simples client
 * are a 404.
 */
class ReadinessController extends Controller
{
    private const NOT_FOUND = 'fiscal.notFound';

    public function __construct(private ReadinessService $readinessService) {}

    public function index(ReadinessIndexRequest $request): JsonResponse
    {
        $list = $this->readinessService->list(ReadinessFilterDTO::from($request));

        return ApiResponse::success(data: [
            'summary' => $list['summary'],
            'systems' => $list['systems'],
            'clients' => ReadinessClientResource::collection($list['clients']),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $detail = $this->readinessService->show($id);

        return $detail === null ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new ReadinessDetailResource($detail));
    }

    public function notice(Request $request, int $id): JsonResponse
    {
        $event = $this->readinessService->notice($id, (int) $request->user()->id);

        return $event === null ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new ClientEventResource($event));
    }
}
