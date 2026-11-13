<?php

namespace App\Modules\Pipeline\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Pipeline\DTOs\LeadBoardFilterDTO;
use App\Modules\Pipeline\DTOs\LeadNoteDTO;
use App\Modules\Pipeline\DTOs\MoveLeadStageDTO;
use App\Modules\Pipeline\Requests\ListLeadsRequest;
use App\Modules\Pipeline\Requests\StoreLeadNoteRequest;
use App\Modules\Pipeline\Requests\UpdateLeadStageRequest;
use App\Modules\Pipeline\Resources\BrandOptionResource;
use App\Modules\Pipeline\Resources\LeadCardResource;
use App\Modules\Pipeline\Resources\LeadDetailResource;
use App\Modules\Pipeline\Resources\LeadEventResource;
use App\Modules\Pipeline\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The team's board and lead detail (rnc-1 R18–R19). Another firm's lead is a 404, never a 403. */
class PipelineLeadController extends Controller
{
    private const NOT_FOUND = 'pipeline.notFound';

    public function __construct(private PipelineService $pipelineService) {}

    public function index(ListLeadsRequest $request): JsonResponse
    {
        $columns = $this->pipelineService->board(LeadBoardFilterDTO::from($request));

        return ApiResponse::success(data: [
            'columns' => array_map(fn (array $column) => [...$column, 'items' => LeadCardResource::collection($column['items'])], $columns),
            'brands' => BrandOptionResource::collection($this->pipelineService->brandOptions()),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $detail = $this->pipelineService->detail($id);

        return ! $detail ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new LeadDetailResource($detail));
    }

    public function updateStage(UpdateLeadStageRequest $request, int $id): JsonResponse
    {
        $moved = $this->pipelineService->move($id, MoveLeadStageDTO::from($request), $this->person($request));

        return ! $moved ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: [
            'lead' => new LeadCardResource($moved['card']),
            'event' => new LeadEventResource($moved['event']),
        ]);
    }

    public function storeNote(StoreLeadNoteRequest $request, int $id): JsonResponse
    {
        $note = $this->pipelineService->addNote($id, LeadNoteDTO::from($request)->text, $this->person($request));

        return ! $note ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new LeadEventResource($note));
    }

    private function person(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
