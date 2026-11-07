<?php

namespace App\Modules\Pipeline\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Pipeline\DTOs\AgendaWeekDTO;
use App\Modules\Pipeline\Requests\AgendaRequest;
use App\Modules\Pipeline\Resources\AgendaWeekResource;
use App\Modules\Pipeline\Services\AgendaService;
use Illuminate\Http\JsonResponse;

/** The team's agenda week (rnc-1 R20). */
class AgendaController extends Controller
{
    public function __construct(private AgendaService $agendaService) {}

    public function index(AgendaRequest $request): JsonResponse
    {
        return ApiResponse::success(data: new AgendaWeekResource($this->agendaService->week(AgendaWeekDTO::from($request)->week)));
    }
}
