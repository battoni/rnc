<?php

namespace App\Modules\Idea\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Idea\DTOs\IdeaMergeDTO;
use App\Modules\Idea\DTOs\IdeaStatusDTO;
use App\Modules\Idea\Requests\MergeIdeaRequest;
use App\Modules\Idea\Requests\UpdateIdeaStatusRequest;
use App\Modules\Idea\Resources\IdeaResource;
use App\Modules\Idea\Services\IdeaModerationService;
use Illuminate\Http\JsonResponse;

class IdeaModerationController extends Controller
{
    private const NOT_FOUND = 'Idea not found';

    public function __construct(
        private IdeaModerationService $moderationService,
    ) {}

    public function updateStatus(int $id, UpdateIdeaStatusRequest $request): JsonResponse
    {
        $idea = $this->moderationService->updateStatus($id, IdeaStatusDTO::from($request));

        return ! $idea ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($idea));
    }

    public function hide(int $id): JsonResponse
    {
        $idea = $this->moderationService->toggleHidden($id);

        return ! $idea ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($idea));
    }

    /**
     * Returns the CANONICAL idea, which is the row whose count just changed. A missing duplicate
     * is 404; the merge rules refuse with a 422 from the service, each naming its i18n key.
     */
    public function merge(int $id, MergeIdeaRequest $request): JsonResponse
    {
        $target = $this->moderationService->merge($id, IdeaMergeDTO::from($request));

        return ! $target ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new IdeaResource($target));
    }
}
