<?php

namespace App\Modules\Platform\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Models\Firm;
use App\Modules\Platform\Requests\UploadLogoRequest;
use App\Modules\Platform\Resources\PlatformFirmResource;
use App\Modules\Platform\Services\PlatformFirmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * A firm's logo from the console (rnc-4 R27, D22): `platform.firms` + the firm-less operator. Upload
 * replaces the file (the old one deleted), delete goes back to the logo text; both answer the firm
 * with its `logo_url`. An unknown id is 404 `firms.notFound`.
 */
class FirmLogoController extends Controller
{
    private const NOT_FOUND = 'firms.notFound';

    public function __construct(private PlatformFirmService $platformFirmService) {}

    public function store(UploadLogoRequest $request, int $id): JsonResponse
    {
        /** @var UploadedFile $file the request validated it */
        $file = $request->file('logo');

        return $this->answer($this->platformFirmService->replaceLogo($id, $file));
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->answer($this->platformFirmService->removeLogo($id));
    }

    /** @param  array{firm: Firm, lead_count: int}|null  $firm */
    private function answer(?array $firm): JsonResponse
    {
        return ! $firm ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PlatformFirmResource($firm));
    }
}
