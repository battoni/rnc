<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Firm\Services\FirmSettingsService;
use App\Modules\Partner\DTOs\LedgerMonthDTO;
use App\Modules\Partner\DTOs\UpdateLedgerModelDTO;
use App\Modules\Partner\Requests\LedgerMonthRequest;
use App\Modules\Partner\Requests\UpdateLedgerModelRequest;
use App\Modules\Partner\Resources\LedgerModelResource;
use App\Modules\Partner\Resources\NetworkStatementResource;
use App\Modules\Partner\Services\LedgerService;
use Illuminate\Http\JsonResponse;

/**
 * Franco's network ledger (rnc-2 R11, D10): the month behind `ledger.read`, the model switch behind
 * `partners.manage`. Both act on the signed-in person's firm (`CurrentFirm`), never a header's.
 */
class LedgerController extends Controller
{
    private const NO_FIRM = 'ledger.noFirm';

    public function __construct(
        private LedgerService $ledgerService,
        private FirmSettingsService $firmSettingsService,
    ) {}

    public function index(LedgerMonthRequest $request): JsonResponse
    {
        $month = LedgerMonthDTO::from($request)->month ?? LedgerService::currentMonth();

        return ApiResponse::success(data: new NetworkStatementResource($this->ledgerService->networkStatement($month)));
    }

    public function updateModel(UpdateLedgerModelRequest $request): JsonResponse
    {
        $firm = $this->firmSettingsService->updatePartnerMoneyModel(UpdateLedgerModelDTO::from($request)->model);

        return ! $firm ? ApiResponse::notFound(message: self::NO_FIRM) : ApiResponse::success(data: new LedgerModelResource($firm));
    }
}
