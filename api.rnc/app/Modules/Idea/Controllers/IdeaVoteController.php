<?php

namespace App\Modules\Idea\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Idea\Resources\IdeaResource;
use App\Modules\Idea\Services\IdeaVoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class IdeaVoteController extends Controller
{
    private const NOT_FOUND = 'Idea not found';

    public function __construct(
        private IdeaVoteService $voteService,
    ) {}

    /**
     * Both verbs return the whole idea, not just a count. The board updates a card granularly
     * from the response, and a bare number would leave the client deriving the rest.
     */
    public function store(int $id): JsonResponse
    {
        $idea = $this->voteService->vote(Auth::user(), $id);

        return ! $idea ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($idea));
    }

    public function destroy(int $id): JsonResponse
    {
        $idea = $this->voteService->unvote(Auth::user(), $id);

        return ! $idea ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($idea));
    }
}
