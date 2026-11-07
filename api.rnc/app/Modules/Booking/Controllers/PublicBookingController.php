<?php

namespace App\Modules\Booking\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Booking\DTOs\BookMeetingDTO;
use App\Modules\Booking\Requests\BookMeetingRequest;
use App\Modules\Booking\Resources\PublicAvailabilityResource;
use App\Modules\Booking\Resources\PublicBookingResource;
use App\Modules\Booking\Services\BookingService;
use Illuminate\Http\JsonResponse;

/**
 * The slot picker (rnc-1 R15, R16), by lead token. An unknown, expired or other firm's token is
 * the same calm 404 as on the diagnostic's routes.
 */
class PublicBookingController extends Controller
{
    private const NOT_FOUND = 'diagnostic.notFound';

    public function __construct(private BookingService $bookingService) {}

    public function availability(string $token): JsonResponse
    {
        $availability = $this->bookingService->availability($token);

        return ! $availability ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PublicAvailabilityResource($availability));
    }

    public function store(BookMeetingRequest $request, string $token): JsonResponse
    {
        $booking = $this->bookingService->book($token, BookMeetingDTO::from($request));

        return ! $booking ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::created(data: new PublicBookingResource($booking));
    }
}
