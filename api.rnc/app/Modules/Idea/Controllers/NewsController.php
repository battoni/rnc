<?php

namespace App\Modules\Idea\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Idea\DTOs\NewsStoreDTO;
use App\Modules\Idea\DTOs\NewsUpdateDTO;
use App\Modules\Idea\Enums\NewsReactionType;
use App\Modules\Idea\Requests\PublishNewsRequest;
use App\Modules\Idea\Requests\ReactToNewsRequest;
use App\Modules\Idea\Requests\StoreNewsRequest;
use App\Modules\Idea\Requests\UpdateNewsRequest;
use App\Modules\Idea\Resources\NewsReaderResource;
use App\Modules\Idea\Resources\NewsResource;
use App\Modules\Idea\Services\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NewsController extends Controller
{
    private const NOT_FOUND = 'News entry not found';

    public function __construct(
        private NewsService $newsService,
    ) {}

    public function index(): JsonResponse
    {
        $entries = $this->newsService->list(Auth::user());

        return ApiResponse::success(data: NewsResource::collection($entries));
    }

    public function store(StoreNewsRequest $request): JsonResponse
    {
        $news = $this->newsService->create(Auth::user(), NewsStoreDTO::from($request));

        return ApiResponse::created(data: new NewsResource($news));
    }

    public function update(int $id, UpdateNewsRequest $request): JsonResponse
    {
        $news = $this->newsService->update(Auth::user(), $id, NewsUpdateDTO::from($request));

        return ! $news ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new NewsResource($news));
    }

    public function publish(int $id, PublishNewsRequest $request): JsonResponse
    {
        $news = $this->newsService->setPublished(Auth::user(), $id, $request->boolean('publish'));

        return ! $news ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new NewsResource($news));
    }

    public function read(int $id, Request $request): JsonResponse
    {
        $news = $this->newsService->markRead($request->user(), $id);

        return ! $news ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new NewsResource($news));
    }

    public function readers(int $id): JsonResponse
    {
        $readers = $this->newsService->readers($id);

        return $readers === null ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: NewsReaderResource::collection($readers));
    }

    public function react(int $id, ReactToNewsRequest $request): JsonResponse
    {
        $news = $this->newsService->react($request->user(), $id, NewsReactionType::from($request->string('type')));

        return ! $news ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new NewsResource($news));
    }

    /** The nav dot. One integer, asked for on every page load, so it is its own cheap endpoint. */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(data: ['unread' => $this->newsService->unreadCount($request->user())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $news = $this->newsService->delete($id);

        return ! $news ?
            ApiResponse::notFound(message: self::NOT_FOUND) :
            ApiResponse::success(data: new NewsResource($news));
    }
}
