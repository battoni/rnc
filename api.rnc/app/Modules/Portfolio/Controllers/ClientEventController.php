<?php

namespace App\Modules\Portfolio\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Portfolio\Resources\ClientEventResource;
use App\Modules\Portfolio\Services\ClientService;
use Illuminate\Http\JsonResponse;

/** A client's timeline (rnc-2 R20, R22), behind `fiscal.read`. Another firm's client is a 404. */
class ClientEventController extends Controller
{
    private const NOT_FOUND = 'portfolio.notFound';

    public function __construct(private ClientService $clientService) {}

    public function index(int $id): JsonResponse
    {
        $events = $this->clientService->events($id);

        return $events === null ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: ClientEventResource::collection($events));
    }
}
