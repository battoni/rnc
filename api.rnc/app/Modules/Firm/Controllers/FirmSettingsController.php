<?php

namespace App\Modules\Firm\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\DTOs\UpdateFirmContactDTO;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Requests\UpdateFirmSettingsRequest;
use App\Modules\Firm\Resources\FirmSettingsResource;
use App\Modules\Firm\Services\FirmSettingsService;
use Illuminate\Http\JsonResponse;

/**
 * The firm's own settings, its manager's (rnc-4 R30, `firm.settings`): contact data, business hours
 * and the credentials line of the CURRENT firm — the signed-in person's. No id in the route, so no
 * other firm can be named.
 */
class FirmSettingsController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private FirmSettingsService $firmSettingsService) {}

    public function show(): JsonResponse
    {
        return $this->answer($this->firmSettingsService->current());
    }

    public function update(UpdateFirmSettingsRequest $request): JsonResponse
    {
        return $this->answer($this->firmSettingsService->updateContact(UpdateFirmContactDTO::from($request)));
    }

    private function answer(?Firm $firm): JsonResponse
    {
        return ! $firm ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new FirmSettingsResource($firm));
    }
}
