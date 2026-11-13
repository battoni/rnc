<?php

namespace App\Modules\Tracking\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Tracking\DTOs\RecordEventDTO;
use App\Modules\Tracking\Requests\RecordEventRequest;
use App\Modules\Tracking\Services\TrackingService;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    public function __construct(private TrackingService $trackingService) {}

    /** Answers with nothing: a public endpoint has no reason to echo what a browser sent. */
    public function store(RecordEventRequest $request): JsonResponse
    {
        $event = $this->trackingService->record(RecordEventDTO::from($request));

        return ! $event ? ApiResponse::notFound(message: 'firms.notFound') : ApiResponse::created();
    }
}
