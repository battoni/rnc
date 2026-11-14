<?php

namespace App\Modules\Platform\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Services\PlatformSettings;
use App\Modules\Platform\Requests\UpdateConsentDefaultRequest;
use Illuminate\Http\JsonResponse;

/**
 * The platform's default consent sentence (rnc-4 R13, R25): `platform.consent` + the firm-less
 * operator. Written through the Firm module's `PlatformSettings`, its only writer; a firm's own
 * versions are never touched here.
 */
class PlatformConsentController extends Controller
{
    public function __construct(private PlatformSettings $platformSettings) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success(data: ['text' => $this->platformSettings->consentDefault()]);
    }

    public function update(UpdateConsentDefaultRequest $request): JsonResponse
    {
        return ApiResponse::success(data: ['text' => $this->platformSettings->updateConsentDefault((string) $request->validated('text'))]);
    }
}
