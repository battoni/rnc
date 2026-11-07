<?php

namespace App\Modules\Tip\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Tip\DTOs\TipStoreDTO;
use App\Modules\Tip\DTOs\TipUpdateDTO;
use App\Modules\Tip\Requests\ArchiveTipRequest;
use App\Modules\Tip\Requests\StoreTipRequest;
use App\Modules\Tip\Requests\UpdateTipRequest;
use App\Modules\Tip\Resources\TipContentResource;
use App\Modules\Tip\Resources\TipHistoryResource;
use App\Modules\Tip\Resources\TipResource;
use App\Modules\Tip\Services\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct(
        private TipService $tipService
    ) {}

    /**
     * The payload that renders tips, for ANY authenticated user.
     *
     * Not gated on `tips.read`: a tip is ambient help attached to a screen, so gating it would
     * hide the help from precisely the people who need it while leaving the icon's slot empty.
     * `tips.read` gates the management view, which is a different question.
     */
    public function active(): JsonResponse
    {
        return ApiResponse::success(data: TipContentResource::collection($this->tipService->active()));
    }

    public function index(Request $request): JsonResponse
    {
        $includeArchived = filter_var($request->query('include_archived', false), FILTER_VALIDATE_BOOLEAN);

        return ApiResponse::success(data: TipResource::collection($this->tipService->list($includeArchived)));
    }

    public function show(int $id): JsonResponse
    {
        $tip = $this->tipService->find($id);

        return ! $tip
            ? ApiResponse::notFound(message: 'Tip not found')
            : ApiResponse::success(data: new TipResource($tip));
    }

    public function history(int $id): JsonResponse
    {
        if (! $this->tipService->find($id)) {
            return ApiResponse::notFound(message: 'Tip not found');
        }

        return ApiResponse::success(data: TipHistoryResource::collection($this->tipService->history($id)));
    }

    public function store(StoreTipRequest $request): JsonResponse
    {
        $tip = $this->tipService->create(TipStoreDTO::from($request), auth()->id());

        return ApiResponse::created(data: new TipResource($tip));
    }

    public function update(int $id, UpdateTipRequest $request): JsonResponse
    {
        $tip = $this->tipService->update($id, TipUpdateDTO::from($request), auth()->id());

        return ! $tip
            ? ApiResponse::notFound(message: 'Tip not found')
            : ApiResponse::success(data: new TipResource($tip));
    }

    public function destroy(int $id, ArchiveTipRequest $request): JsonResponse
    {
        $tip = $this->tipService->archive($id, $request->validated('change_reason'), auth()->id());

        return ! $tip
            ? ApiResponse::notFound(message: 'Tip not found or already archived')
            : ApiResponse::success(data: new TipResource($tip));
    }

    public function unarchive(int $id): JsonResponse
    {
        $tip = $this->tipService->unarchive($id, auth()->id());

        return ! $tip
            ? ApiResponse::notFound(message: 'Tip not found or not archived')
            : ApiResponse::success(data: new TipResource($tip));
    }

    public function restore(int $id, int $historyId): JsonResponse
    {
        $tip = $this->tipService->restore($id, $historyId, auth()->id());

        return ! $tip
            ? ApiResponse::notFound(message: 'Tip or version not found')
            : ApiResponse::success(data: new TipResource($tip));
    }
}
