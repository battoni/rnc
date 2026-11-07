<?php

namespace App\Modules\Idea\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Idea\DTOs\IdeaListDTO;
use App\Modules\Idea\DTOs\IdeaStoreDTO;
use App\Modules\Idea\DTOs\IdeaUpdateDTO;
use App\Modules\Idea\Requests\ListIdeasRequest;
use App\Modules\Idea\Requests\StoreIdeaRequest;
use App\Modules\Idea\Requests\UpdateIdeaRequest;
use App\Modules\Idea\Resources\IdeaResource;
use App\Modules\Idea\Services\IdeaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class IdeaController extends Controller
{
    private const NOT_FOUND = 'Idea not found';

    private const FORBIDDEN = 'This idea can no longer be edited';

    public function __construct(
        private IdeaService $ideaService,
    ) {}

    public function index(ListIdeasRequest $request): JsonResponse
    {
        $ideas = $this->ideaService->list(Auth::user(), IdeaListDTO::from($request));

        return ApiResponse::success(data: IdeaResource::collection($ideas));
    }

    public function show(int $id): JsonResponse
    {
        $idea = $this->ideaService->show(Auth::user(), $id);

        return ! $idea ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($idea));
    }

    public function store(StoreIdeaRequest $request): JsonResponse
    {
        $idea = $this->ideaService->create(Auth::user(), IdeaStoreDTO::from($request));

        return ApiResponse::created(data: new IdeaResource($idea));
    }

    /**
     * Two refusals, two answers. An idea the person cannot SEE — missing, or hidden from them —
     * is 404, the same answer `show` gives; a 403 there would confirm the hidden row exists. An
     * idea they can see but may no longer rewrite is 403: pretending it is missing would be a lie
     * they can check on the board.
     */
    public function update(int $id, UpdateIdeaRequest $request): JsonResponse
    {
        $idea = $this->ideaService->findWritable(Auth::user(), $id);

        if (! $idea) {
            return ApiResponse::notFound(message: self::NOT_FOUND);
        }

        $updated = $this->ideaService->update(Auth::user(), $idea, IdeaUpdateDTO::from($request));

        return ! $updated ?
            ApiResponse::forbidden(message: self::FORBIDDEN) :
            ApiResponse::success(data: new IdeaResource($updated));
    }

    public function destroy(int $id): JsonResponse
    {
        $idea = $this->ideaService->findWritable(Auth::user(), $id);

        if (! $idea) {
            return ApiResponse::notFound(message: self::NOT_FOUND);
        }

        $deleted = $this->ideaService->delete(Auth::user(), $idea);

        return ! $deleted ?
            ApiResponse::forbidden(message: self::FORBIDDEN) :
            ApiResponse::success(data: new IdeaResource($deleted));
    }
}
