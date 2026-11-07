<?php

namespace App\Modules\Content\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Content\Resources\PublicSectorPageResource;
use App\Modules\Content\Resources\PublicSectorPageSummaryResource;
use App\Modules\Content\Services\SectorPageService;
use Illuminate\Http\JsonResponse;

class PublicSectorPageController extends Controller
{
    public function __construct(private SectorPageService $sectorPageService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(data: PublicSectorPageSummaryResource::collection($this->sectorPageService->list()));
    }

    public function show(string $slug): JsonResponse
    {
        $page = $this->sectorPageService->findBySlug($slug);

        return ! $page ? ApiResponse::notFound(message: 'content.sectorPage.notFound') : ApiResponse::success(data: new PublicSectorPageResource($page));
    }
}
