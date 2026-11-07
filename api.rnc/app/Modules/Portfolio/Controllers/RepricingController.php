<?php

namespace App\Modules\Portfolio\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Portfolio\DTOs\MarkRepricingDTO;
use App\Modules\Portfolio\DTOs\RepricingFilterDTO;
use App\Modules\Portfolio\Requests\MarkRepricingRequest;
use App\Modules\Portfolio\Requests\RepricingIndexRequest;
use App\Modules\Portfolio\Resources\RepricingClientResource;
use App\Modules\Portfolio\Resources\RepricingDetailResource;
use App\Modules\Portfolio\Resources\RepricingLetterResource;
use App\Modules\Portfolio\Services\RepricingService;
use Illuminate\Http\JsonResponse;

/** Reinaldo's fee repricing (rnc-1 R26). Another firm's client is a 404, never a 403. */
class RepricingController extends Controller
{
    private const NOT_FOUND = 'portfolio.notFound';

    public function __construct(private RepricingService $repricingService) {}

    public function index(RepricingIndexRequest $request): JsonResponse
    {
        $list = $this->repricingService->list(RepricingFilterDTO::from($request));

        return ApiResponse::success(data: [
            'summary' => [
                'clients' => $list['summary']['clients'],
                'current_yearly_cents' => RepricingClientResource::cents($list['summary']['current_yearly']),
                'uplift_total_cents' => RepricingClientResource::cents($list['summary']['uplift_total']),
                'uplift_share' => $list['summary']['uplift_share'],
                'average_pct' => $list['summary']['average_pct'],
                'marked' => $list['summary']['marked'],
            ],
            'spread' => $list['spread'],
            'clients' => RepricingClientResource::collection($list['clients']),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $quote = $this->repricingService->show($id);

        return ! $quote ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new RepricingDetailResource($quote));
    }

    public function letter(int $id): JsonResponse
    {
        $letter = $this->repricingService->letter($id);

        return ! $letter ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new RepricingLetterResource($letter));
    }

    public function mark(MarkRepricingRequest $request): JsonResponse
    {
        $clients = $this->repricingService->mark(MarkRepricingDTO::from($request), $request->user());

        return ApiResponse::success(data: ['marked' => count($clients), 'clients' => RepricingClientResource::collection($clients)]);
    }
}
