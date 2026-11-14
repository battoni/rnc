<?php

namespace App\Modules\Booking\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Booking\DTOs\UpdateSpecialistHoursDTO;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Requests\UpdateSpecialistHoursRequest;
use App\Modules\Booking\Resources\SpecialistHourResource;
use App\Modules\Booking\Resources\SpecialistResource;
use App\Modules\Booking\Services\SpecialistService;
use Illuminate\Http\JsonResponse;

/**
 * The specialists' weekly hours on the manager's settings (rnc-4 R32, `firm.settings`). Firm-scoped by
 * `BelongsToFirm`: another firm's specialist is 404, never 403.
 */
class SpecialistHoursController extends Controller
{
    private const NOT_FOUND = 'booking.specialistNotFound';

    public function __construct(private SpecialistService $specialistService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(data: SpecialistResource::collection($this->specialistService->list()));
    }

    public function show(int $id): JsonResponse
    {
        return $this->answer($this->specialistService->find($id));
    }

    public function update(UpdateSpecialistHoursRequest $request, int $id): JsonResponse
    {
        return $this->answer($this->specialistService->updateHours($id, UpdateSpecialistHoursDTO::from($request)->hours));
    }

    private function answer(?Specialist $specialist): JsonResponse
    {
        return ! $specialist
            ? ApiResponse::notFound(message: self::NOT_FOUND)
            : ApiResponse::success(data: SpecialistHourResource::collection(SpecialistHourResource::inWeekOrder($specialist->hours)));
    }
}
