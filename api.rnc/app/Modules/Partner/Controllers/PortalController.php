<?php

namespace App\Modules\Partner\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Partner\DTOs\LedgerMonthDTO;
use App\Modules\Partner\DTOs\TransitionReferralDTO;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Requests\LedgerMonthRequest;
use App\Modules\Partner\Requests\TransitionReferralRequest;
use App\Modules\Partner\Resources\PartnerStatementResource;
use App\Modules\Partner\Resources\PortalMeResource;
use App\Modules\Partner\Resources\PortalReferralResource;
use App\Modules\Partner\Services\CurrentPartner;
use App\Modules\Partner\Services\LedgerService;
use App\Modules\Partner\Services\PortalReferralService;
use Illuminate\Http\JsonResponse;

/**
 * The partner's portal (rnc-2 R3–R4). Every route is `permission:portal.access` + `portal`, so
 * `CurrentPartner` is always set here; nothing reads a partner id from the request. A referral that
 * is not this partner's — another partner's, another firm's, or no referral at all — is a 404.
 */
class PortalController extends Controller
{
    private const NOT_FOUND = 'portal.referrals.notFound';

    public function __construct(
        private CurrentPartner $currentPartner,
        private PortalReferralService $portalReferralService,
        private LedgerService $ledgerService,
    ) {}

    public function me(): JsonResponse
    {
        return ApiResponse::success(data: new PortalMeResource($this->currentPartner->get()));
    }

    public function referrals(): JsonResponse
    {
        return ApiResponse::success(data: PortalReferralResource::collection($this->portalReferralService->inbox()));
    }

    public function referral(int $id): JsonResponse
    {
        $referral = $this->portalReferralService->show($id);

        return ! $referral ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PortalReferralResource($referral));
    }

    public function transition(TransitionReferralRequest $request, int $id): JsonResponse
    {
        /** @var User $person */
        $person = $request->user();
        $referral = $this->portalReferralService->transition($id, TransitionReferralDTO::from($request->validated()), $person);

        return ! $referral ? ApiResponse::notFound(message: self::NOT_FOUND) : ApiResponse::success(data: new PortalReferralResource($referral));
    }

    /** The partner's own month (R11): its lines under the firm's model, never the other model's. */
    public function statement(LedgerMonthRequest $request): JsonResponse
    {
        /** @var Partner $partner the `portal` alias set it */
        $partner = $this->currentPartner->get();
        $month = LedgerMonthDTO::from($request)->month ?? LedgerService::currentMonth();

        return ApiResponse::success(data: new PartnerStatementResource($this->ledgerService->statementFor($partner, $month)));
    }
}
