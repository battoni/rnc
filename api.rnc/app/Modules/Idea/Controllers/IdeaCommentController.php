<?php

namespace App\Modules\Idea\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Idea\DTOs\IdeaCommentStoreDTO;
use App\Modules\Idea\Requests\StoreIdeaCommentRequest;
use App\Modules\Idea\Resources\IdeaCommentResource;
use App\Modules\Idea\Services\IdeaCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class IdeaCommentController extends Controller
{
    private const NOT_FOUND = 'Idea not found';

    private const COMMENT_NOT_FOUND = 'Comment not found';

    public function __construct(
        private IdeaCommentService $commentService,
    ) {}

    public function index(int $id): JsonResponse
    {
        $comments = $this->commentService->list(Auth::user(), $id);

        return $comments === null ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: IdeaCommentResource::collection($comments));
    }

    public function store(int $id, StoreIdeaCommentRequest $request): JsonResponse
    {
        $comment = $this->commentService->create(Auth::user(), $id, IdeaCommentStoreDTO::from($request));

        return ! $comment ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::created(data: new IdeaCommentResource($comment));
    }

    public function destroy(int $id): JsonResponse
    {
        $comment = $this->commentService->delete(Auth::user(), $id);

        return ! $comment ?
            ApiResponse::notFound(message: self::COMMENT_NOT_FOUND) :
            ApiResponse::success(data: new IdeaCommentResource($comment));
    }
}
